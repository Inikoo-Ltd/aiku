<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Enums\CRM\Livechat\SlackFileStateEnum;
use App\Models\Chat\SlackFile;
use App\Models\Chat\SlackMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * Keeps aiku's own copy of a file shared in a public Slack channel, once per Slack file id.
 * Files linked from elsewhere (Google Drive and the like) are kept as Slack's link only.
 */
class CopySlackMessageFile
{
    use AsAction;
    use WithSlack;

    public const int MAX_BYTES = 50 * 1024 * 1024;

    public const array GONE_MODES = ['tombstone', 'hidden_by_limit'];

    public const array IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public string $jobQueue = 'low-priority';

    public int $jobTries = 3;

    public int $jobBackoff = 900;

    public int $jobTimeout = 600;

    public const int CLAIM_EXPIRES_AFTER_SECONDS = 900;

    /**
     * @param  array<string, mixed>  $file  one entry of the message's data->files
     */
    public function handle(SlackMessage $message, array $file): ?SlackFile
    {
        $slackId = Arr::get($file, 'id');
        if (!$slackId || !$message->channel->is_public) {
            return null;
        }

        $slackFile = SlackFile::firstOrCreate(['slack_id' => $slackId], [
            'group_id'         => $message->channel->group_id,
            'slack_message_id' => $message->id,
            'name'             => Arr::get($file, 'name') ?? Arr::get($file, 'title'),
            'mime_type'        => Arr::get($file, 'mimetype'),
            'size'             => Arr::get($file, 'size'),
            'mode'             => Arr::get($file, 'mode'),
            'state'            => SlackFileStateEnum::PENDING,
            'data'             => $file,
        ]);
        if (!$this->claim($slackFile)) {
            return $slackFile->refresh();
        }
        $slackFile->refresh();

        try {
            if (!self::downloadUrl($file) && !self::isMarkedGone($file)) {
                $file = $this->withFileInfo($slackFile, $file);
            }

            if ($outcome = self::outcomeWithoutDownload($file)) {
                $slackFile->update(['state' => $outcome[0], 'reason' => $outcome[1]]);

                return $slackFile;
            }

            return $this->download($slackFile, (string) self::downloadUrl($file));
        } catch (Throwable $exception) {
            if ($slackFile->state === SlackFileStateEnum::COPYING) {
                $slackFile->update(['state' => SlackFileStateEnum::FAILED, 'reason' => mb_substr($exception->getMessage(), 0, 250)]);
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
            (int) Arr::get($file, 'size') > self::MAX_BYTES => [SlackFileStateEnum::SKIPPED, 'larger than 50 MB'],
            default                                         => null,
        };
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
    private function withFileInfo(SlackFile $slackFile, array $file): array
    {
        if (Arr::get($file, 'mode') === 'external') {
            return $file;
        }

        $response = $this->slackClient()?->get('files.info', ['file' => $slackFile->slack_id]);
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

    private function download(SlackFile $slackFile, string $url): SlackFile
    {
        $token = config('services.slack.notifications.bot_user_oauth_token') ?: throw new RuntimeException('Slack bot token is not set');
        $path  = tempnam(sys_get_temp_dir(), 'slack-file-');

        try {
            $response = Http::withToken($token)->timeout(300)->sink($path)->get($url);

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
            if (filesize($path) > self::MAX_BYTES) {
                $slackFile->update(['state' => SlackFileStateEnum::SKIPPED, 'reason' => 'larger than 50 MB']);

                return $slackFile;
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
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function fail(SlackFile $slackFile, string $reason): never
    {
        $slackFile->update(['state' => SlackFileStateEnum::FAILED, 'reason' => $reason]);

        throw new RuntimeException("Slack file $slackFile->slack_id not copied: $reason");
    }
}
