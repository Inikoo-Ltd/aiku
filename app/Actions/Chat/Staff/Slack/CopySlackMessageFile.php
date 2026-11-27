<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Enums\CRM\Livechat\SlackFileStateEnum;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\Chat\SlackFile;
use App\Models\Chat\SlackMessage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * Keeps aiku's own copy of a file shared in Slack, once per Slack file id and side (public, or private for private channels
 * and DMs, INI-441). Files linked from elsewhere (Google Drive and the like) are kept as Slack's link only.
 * A private channel file is read with the bot token when the bot is in the channel, otherwise with an opted-in member's token;
 * a DM file only with its author's token while the author has opted in, never the bot. Private copies never go to the media
 * library: they are kept on PRIVATE_DISK or the large files disk under an opaque name, and the queued job carries ids only.
 */
class CopySlackMessageFile
{
    use AsAction;
    use WithSlack;

    public const int MAX_BYTES = 50 * 1024 * 1024;

    public const int MAX_LARGE_BYTES = 1024 * 1024 * 1024;

    public const string LARGE_DISK = 'staff-chat-r2';

    public const array GONE_MODES = ['tombstone', 'hidden_by_limit'];

    public const string PRIVATE_DISK = 'local';

    public const string PRIVATE_FOLDER = 'slack-private-files';

    public const array IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public string $jobQueue = 'low-priority';

    public int $jobTries = 3;

    public int $jobBackoff = 900;

    public int $jobTimeout = 600;

    public const int CLAIM_EXPIRES_AFTER_SECONDS = 900;

    /**
     * @param  array<string, mixed>|string  $file  one entry of the message's data->files, or its Slack file id
     */
    public function handle(SlackMessage $message, array|string $file): ?SlackFile
    {
        $file    = is_string($file) ? self::listedFile($message, $file) : $file;
        $slackId = Arr::get((array) $file, 'id');
        $channel = $message->channel;
        if (!$slackId || !$channel || $channel->is_ignored) {
            return null;
        }
        if (!$channel->is_public) {
            return $this->handlePrivate($message, (array) $file);
        }

        return $this->copy($message, (array) $file, $this->slackClient(), config('services.slack.notifications.bot_user_oauth_token'), false);
    }

    /**
     * Queues a copy of each file of a message, by ids only, so no file name or link sits in a queue payload.
     */
    public static function dispatchFor(SlackMessage $message): void
    {
        if ($message->trashed()) {
            return;
        }
        foreach ((array) Arr::get($message->data, 'files', []) as $file) {
            if (is_array($file) && is_string($id = Arr::get($file, 'id')) && $id !== '') {
                self::dispatch($message->id, $id);
            }
        }
    }

    /**
     * Queued with ids only (dispatch($message->id, $slackFileId)); a model and file entry queued before INI-441 still run.
     *
     * @param  array<string, mixed>|string  $file
     */
    public function asJob(SlackMessage|int $message, array|string $file): void
    {
        if ($message = $message instanceof SlackMessage ? $message : SlackMessage::find($message)) {
            $this->handle($message, $file);
        }
    }

    /**
     * @param  array<string, mixed>  $file
     */
    private function handlePrivate(SlackMessage $message, array $file): ?SlackFile
    {
        $token = self::privateReadingToken($message);
        if (!$token) {
            return null;
        }

        try {
            return $this->copy($message, $file, Http::withToken($token)->baseUrl('https://slack.com/api'), $token, true);
        } catch (Throwable $exception) {
            throw new RuntimeException('Slack private file '.$file['id'].' not copied: '.$exception::class);
        }
    }

    /**
     * The token a private message's files may be read with, chosen at the moment of use: a private channel's by the bot when it
     * is in it, else by an opted-in member; a DM's only by its author while the author has opted in, never the bot.
     */
    public static function privateReadingToken(SlackMessage $message): ?string
    {
        $channel = $message->channel;
        if (!$channel || $message->data === null) {
            return null;
        }
        $isDirect = SlackChannel::keptDirect()->whereKey($channel->id)->exists();
        if (!$isDirect && !SlackChannel::keptPrivate()->whereKey($channel->id)->exists()) {
            return null;
        }
        if (!$isDirect && $channel->is_member) {
            return config('services.slack.notifications.bot_user_oauth_token') ?: null;
        }
        if (!app()->environment(SlackWriteAccess::WRITE_ENVIRONMENTS)) {
            return null;
        }
        $connection = SyncSlackPrivateChannels::usableConnectionAmong($isDirect ? array_filter([$message->slack_user_id]) : $channel->members()->select('slack_user_id'));

        return $connection ? self::connectionToken($connection) : null;
    }

    private static function connectionToken(SlackConnection $connection): ?string
    {
        try {
            return (string) $connection->token ?: null;
        } catch (DecryptException) {
            $connection->forceFill(['needs_reconnect' => true])->save();

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function listedFile(SlackMessage $message, string $slackFileId): ?array
    {
        return collect(is_array($message->data['files'] ?? null) ? $message->data['files'] : [])
            ->first(fn ($file) => is_array($file) && ($file['id'] ?? null) === $slackFileId);
    }

    /**
     * @param  array<string, mixed>  $file
     */
    private function copy(SlackMessage $message, array $file, ?PendingRequest $client, ?string $token, bool $isPrivate): SlackFile
    {
        $slackId   = (string) $file['id'];
        $slackFile = SlackFile::firstOrCreate(['slack_id' => $slackId, 'is_private' => $isPrivate], [
            'group_id'         => $message->channel->group_id,
            'slack_message_id' => $message->id,
            'name'             => Arr::get($file, 'name') ?? Arr::get($file, 'title'),
            'mime_type'        => Arr::get($file, 'mimetype'),
            'size'             => Arr::get($file, 'size'),
            'mode'             => Arr::get($file, 'mode'),
            'state'            => SlackFileStateEnum::PENDING,
            'data'             => $file,
        ]);
        if ($slackFile->slack_message_id === null) {
            $slackFile->update(['slack_message_id' => $message->id]);
        }
        // shortcut: a file uploaded from aiku whose message never arrives stays an orphan row, prune when it matters
        if (!$this->claim($slackFile)) {
            return $slackFile->refresh();
        }
        $slackFile->refresh();

        try {
            if (!self::downloadUrl($file) && !self::isMarkedGone($file)) {
                $file = $this->withFileInfo($slackFile, $file, $client);
            }

            if ($outcome = self::outcomeWithoutDownload($file)) {
                $slackFile->update(['state' => $outcome[0], 'reason' => $outcome[1]]);

                return $slackFile;
            }

            return $this->download($slackFile, (string) self::downloadUrl($file), $token, $isPrivate ? $message : null);
        } catch (Throwable $exception) {
            if ($slackFile->state === SlackFileStateEnum::COPYING) {
                $slackFile->update(['state' => SlackFileStateEnum::FAILED, 'reason' => $isPrivate ? $exception::class : mb_substr($exception->getMessage(), 0, 250)]);
            }

            throw $exception;
        }
    }

    /**
     * One job at a time copies a file, on any server; a claim left by a job killed mid-copy can be taken again once expired.
     */
    private function claim(SlackFile $slackFile): bool
    {
        return SlackFile::whereKey($slackFile->id)
            ->where(fn ($query) => $query->whereIn('state', [SlackFileStateEnum::PENDING, SlackFileStateEnum::FAILED])
                ->orWhere(fn ($query) => $query->where('state', SlackFileStateEnum::COPYING)->where('updated_at', '<', now()->subSeconds(self::CLAIM_EXPIRES_AFTER_SECONDS))))
            ->update(['state' => SlackFileStateEnum::COPYING, 'updated_at' => now()]) === 1;
    }

    /**
     * What happens to a file that must not or cannot be downloaded, read from what Slack says about it; null when it is to be downloaded.
     *
     * @param  array<string, mixed>  $file
     * @return array{0: SlackFileStateEnum, 1: string|null}|null
     */
    public static function outcomeWithoutDownload(array $file): ?array
    {
        $mode = Arr::get($file, 'mode');
        $url  = self::downloadUrl($file);

        return match (true) {
            $mode === 'external'                            => [SlackFileStateEnum::EXTERNAL, null],
            self::isMarkedGone($file)                       => [SlackFileStateEnum::NOT_AVAILABLE, 'Slack shows it as '.(in_array($mode, self::GONE_MODES, true) ? $mode : Arr::get($file, 'file_access'))],
            !$url                                           => [SlackFileStateEnum::NOT_AVAILABLE, 'no download link'],
            !self::isSlackFileUrl($url)                     => [SlackFileStateEnum::SKIPPED, 'download link is not on slack.com'],
            (int) Arr::get($file, 'size') > self::maxBytes() => [SlackFileStateEnum::SKIPPED, self::tooLargeReason()],
            default                                         => null,
        };
    }

    /**
     * The media library holds copies up to its own limit; larger ones, up to 1 GB, go to the large files disk when it is set up.
     */
    public static function mediaLimit(): int
    {
        return min(self::MAX_BYTES, (int) config('media-library.max_file_size'));
    }

    public static function hasLargeDisk(): bool
    {
        return (bool) config('filesystems.disks.'.self::LARGE_DISK.'.bucket');
    }

    public static function maxBytes(): int
    {
        return self::hasLargeDisk() ? self::MAX_LARGE_BYTES : self::mediaLimit();
    }

    public static function tooLargeReason(): string
    {
        return self::hasLargeDisk() ? 'larger than 1 GB' : 'larger than 50 MB';
    }

    /**
     * True when what Slack already said about the file settles it without asking Slack anything more.
     *
     * @param  array<string, mixed>  $file
     */
    public static function isSettledWithoutSlack(array $file): bool
    {
        return (self::downloadUrl($file) || self::isMarkedGone($file) || Arr::get($file, 'mode') === 'external') && self::outcomeWithoutDownload($file) !== null;
    }

    /**
     * @param  array<string, mixed>  $file
     */
    public static function isMarkedGone(array $file): bool
    {
        return in_array(Arr::get($file, 'mode'), self::GONE_MODES, true) || in_array(Arr::get($file, 'file_access'), ['file_not_found', 'file_deleted'], true);
    }

    /**
     * A message can carry only the id of a file (file_access check_file_info); Slack gives the rest through files.info.
     *
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    private function withFileInfo(SlackFile $slackFile, array $file, ?PendingRequest $client): array
    {
        if (Arr::get($file, 'mode') === 'external') {
            return $file;
        }

        $response = $client?->get('files.info', ['file' => $slackFile->slack_id]);
        $error    = $response ? $response->json('error') : 'no Slack bot token';
        if (in_array($error, ['file_not_found', 'file_deleted'], true)) {
            return ['file_access' => $error] + $file;
        }
        if (!$response?->json('ok')) {
            $this->fail($slackFile, 'files.info: '.($error ?? 'http '.$response?->status()));
        }

        $file = array_merge($file, Arr::except((array) $response->json('file'), ['shares', 'channels', 'groups', 'ims']));
        $slackFile->update([
            'name'      => Arr::get($file, 'name') ?? Arr::get($file, 'title'),
            'mime_type' => Arr::get($file, 'mimetype'),
            'size'      => Arr::get($file, 'size'),
            'mode'      => Arr::get($file, 'mode'),
            'data'      => $file,
        ]);

        return $file;
    }

    /**
     * @param  array<string, mixed>  $file
     */
    public static function downloadUrl(array $file): ?string
    {
        return Arr::get($file, 'url_private_download') ?? Arr::get($file, 'url_private');
    }

    private static function isSlackFileUrl(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return parse_url($url, PHP_URL_SCHEME) === 'https' && ($host === 'slack.com' || str_ends_with($host, '.slack.com'));
    }

    /**
     * A private copy is downloaded with the token chosen for it and kept only while the message still lists the file,
     * so a DM purged during the download keeps nothing.
     */
    private function download(SlackFile $slackFile, string $url, ?string $token, ?SlackMessage $privateMessage): SlackFile
    {
        $token = $token ?: throw new RuntimeException('Slack bot token is not set');
        $path  = tempnam(sys_get_temp_dir(), 'slack-file-');

        try {
            $response = Http::withToken($token)->timeout(540)->sink($path)->get($url);

            if (in_array($response->status(), [404, 410], true)) {
                $slackFile->update(['state' => SlackFileStateEnum::NOT_AVAILABLE, 'reason' => 'gone in Slack (http '.$response->status().')']);

                return $slackFile;
            }
            if ($response->failed()) {
                $this->fail($slackFile, 'http '.$response->status());
            }
            if (str_contains(strtolower((string) $response->header('Content-Type')), 'text/html') && !str_contains((string) $slackFile->mime_type, 'html')) {
                $this->fail($slackFile, 'Slack answered with a login page, the bot token has no access to the file');
            }
            if ($privateMessage) {
                $stillListed = ($fresh = SlackMessage::find($privateMessage->id)) && self::listedFile($fresh, $slackFile->slack_id);
                if (!$stillListed) {
                    $slackFile->update(['state' => SlackFileStateEnum::PENDING, 'reason' => null]);

                    return $slackFile;
                }

                return $this->keepPrivately($slackFile, $path);
            }

            return $this->keepFromPath($slackFile, $path);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Keeps the bytes at $path as aiku's copy: media up to its limit, the large files disk above it, skipped above that.
     */
    public function keepFromPath(SlackFile $slackFile, string $path): SlackFile
    {
        if (filesize($path) > self::maxBytes()) {
            $slackFile->update(['state' => SlackFileStateEnum::SKIPPED, 'reason' => self::tooLargeReason()]);

            return $slackFile;
        }
        if (filesize($path) > self::mediaLimit()) {
            return $this->keepOnLargeDisk($slackFile, $path);
        }

        $isImage   = in_array($slackFile->mime_type, self::IMAGE_MIME_TYPES, true);
        $extension = pathinfo((string) $slackFile->name, PATHINFO_EXTENSION) ?: Arr::get($slackFile->data, 'filetype') ?: 'bin';
        $media     = StoreMediaFromFile::run($slackFile, [
            'path'         => $path,
            'originalName' => $slackFile->name ?: $slackFile->slack_id,
            'extension'    => $extension,
            'checksum'     => md5_file($path),
        ], 'slack_files', $isImage ? 'image' : 'file');

        $slackFile->update(['state' => SlackFileStateEnum::COPIED, 'reason' => null, 'media_id' => $media->id]);

        return $slackFile;
    }

    /**
     * The large files disk when it is set up (shared by every server, streamed through aiku and never given a signed link
     * for these), otherwise the server's own private disk; the name is a random ulid, the real one stays on the row.
     */
    public static function privateDisk(): string
    {
        return self::hasLargeDisk() ? self::LARGE_DISK : self::PRIVATE_DISK;
    }

    private function keepPrivately(SlackFile $slackFile, string $path): SlackFile
    {
        if (filesize($path) > self::maxBytes()) {
            $slackFile->update(['state' => SlackFileStateEnum::SKIPPED, 'reason' => self::tooLargeReason()]);

            return $slackFile;
        }
        $disk   = self::privateDisk();
        $target = self::PRIVATE_FOLDER.'/'.strtolower((string) Str::ulid());
        $stream = fopen($path, 'r');
        try {
            $written = Storage::disk($disk)->writeStream($target, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if ($written === false) {
            $this->fail($slackFile, 'not written to the private disk');
        }
        $slackFile->update(['state' => SlackFileStateEnum::COPIED, 'reason' => null, 'disk' => $disk, 'path' => $target, 'size' => filesize($path)]);

        return $slackFile;
    }

    private function keepOnLargeDisk(SlackFile $slackFile, string $path): SlackFile
    {
        $target = 'slack-files/'.$slackFile->slack_id.'/'.Str::slug(pathinfo((string) $slackFile->name, PATHINFO_FILENAME) ?: 'file');
        if ($extension = pathinfo((string) $slackFile->name, PATHINFO_EXTENSION) ?: Arr::get($slackFile->data, 'filetype')) {
            $target .= '.'.strtolower((string) $extension);
        }

        $stream = fopen($path, 'r');
        try {
            Storage::disk(self::LARGE_DISK)->writeStream($target, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $slackFile->update(['state' => SlackFileStateEnum::COPIED, 'reason' => null, 'disk' => self::LARGE_DISK, 'path' => $target, 'size' => filesize($path)]);

        return $slackFile;
    }

    private function fail(SlackFile $slackFile, string $reason): never
    {
        $slackFile->update(['state' => SlackFileStateEnum::FAILED, 'reason' => $reason]);

        throw new RuntimeException("Slack file $slackFile->slack_id not copied: $reason");
    }
}
