<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\CopySlackMessageFile;
use App\Enums\CRM\Livechat\SlackFileStateEnum;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackFile;
use App\Models\SysAdmin\User;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * First half of sending a file to Slack as the signed-in person, one file per request so the box can show each file's
 * progress and retry only the one that failed: Slack's files.getUploadURLExternal, the bytes posted to the address it
 * returns, and aiku's own copy kept at once under the Slack file id (so CopySlackMessageFile has nothing left to do when
 * the file_share event arrives). The file is not in the channel until SendSlackFiles completes it. Needs the person's own
 * connection: the Desk bot's stand-in is not offered an upload.
 */
class UploadSlackFile
{
    use AsAction;
    use WithSlackMessageWrite;

    // shortcut: Octane kills a web request at 45 s, so a file is relayed to Slack inside that; larger files need a path that bypasses a web worker, INI-491 part 5
    public const int MAX_BYTES = 25 * 1024 * 1024;

    public const int PENDING_SECONDS = 3600;

    public const array REFUSED_TYPES = ['text/html', 'image/svg+xml', 'application/x-httpd-php', 'application/x-msdownload', 'application/x-dosexec', 'application/x-executable', 'application/x-sh', 'application/x-mach-binary'];

    public const array ALLOWED_TYPES = [
        'application/pdf', 'application/zip', 'application/x-7z-compressed', 'application/vnd.rar', 'application/gzip', 'application/json', 'application/rtf',
        'application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint', 'application/vnd.ms-outlook', 'application/xml',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.spreadsheet', 'application/vnd.oasis.opendocument.presentation',
    ];

    protected function writesDirect(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'file.required' => __('Choose a file first.'),
            'file.file'     => __('The file did not arrive. It may be larger than this server accepts (:size).', ['size' => self::limitLabel()]),
        ];
    }

    /**
     * The biggest file the box may send: aiku's cap, or less when PHP would refuse the request first.
     */
    public static function maxBytes(): int
    {
        $php = array_filter([ini_parse_quantity((string) ini_get('upload_max_filesize')), ini_parse_quantity((string) ini_get('post_max_size'))]);

        return min(self::MAX_BYTES, ...($php ?: [self::MAX_BYTES]));
    }

    public static function limitLabel(): string
    {
        return round(self::maxBytes() / 1024 / 1024).' MB';
    }

    public static function isAllowedType(string $mime): bool
    {
        if (in_array($mime, self::REFUSED_TYPES, true)) {
            return false;
        }

        return Str::startsWith($mime, ['image/', 'audio/', 'video/', 'text/']) || in_array($mime, self::ALLOWED_TYPES, true);
    }

    public static function cacheKey(User $user, SlackChannel $slackChannel, string $fileId): string
    {
        return 'slack-upload:'.$user->id.':'.$slackChannel->id.':'.$fileId;
    }

    /**
     * @return array{file_id: string, name: string, size: int, mime_type: string}
     */
    public function handle(User $user, SlackChannel $slackChannel, UploadedFile $file): array
    {
        $this->ensureMayWrite($user, $slackChannel);
        if (!$slackChannel->direct_type && !SlackChannel::kept()->whereKey($slackChannel->id)->exists()) {
            $this->refuse(__('Files cannot be sent to this channel.'));
        }

        $size = (int) $file->getSize();
        if ($size < 1) {
            $this->refuse(__('This file is empty.'));
        }
        if ($size > self::maxBytes()) {
            $this->refuse(__('This file is larger than the :size aiku can send to Slack.', ['size' => self::limitLabel()]));
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (!self::isAllowedType($mime)) {
            $this->refuse(__('Slack files of this type cannot be sent from aiku.'));
        }

        $client = $this->clientFor($user, channel: $slackChannel);
        $name   = Str::limit(trim(basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'file', 200, '');

        $upload = $this->callSlack($client, 'files.getUploadURLExternal', ['filename' => $name, 'length' => $size]);
        $url    = (string) ($upload['upload_url'] ?? '');
        $id     = (string) ($upload['file_id'] ?? '');
        if ($id === '' || !str_ends_with((string) parse_url($url, PHP_URL_HOST), '.slack.com') || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            $this->refuse(self::sentenceFor(null));
        }

        $this->sendBytes($url, $file, $mime);
        $this->keepCopy($slackChannel, $id, $name, $mime, $size, $file);
        Cache::put(self::cacheKey($user, $slackChannel, $id), $name, self::PENDING_SECONDS);

        return ['file_id' => $id, 'name' => $name, 'size' => $size, 'mime_type' => $mime];
    }

    private function sendBytes(string $url, UploadedFile $file, string $mime): void
    {
        $handle = fopen($file->getRealPath(), 'r');
        try {
            $response = Http::timeout(600)->withBody(Utils::streamFor($handle), $mime)->post($url);
        } catch (Throwable $exception) {
            report($exception);
            $this->refuse(__('Slack could not be reached. Try again in a minute'));
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
        if ($response->failed()) {
            $this->refuse(__('Slack did not accept this file. Try again.'));
        }
    }

    /**
     * A DM file is not kept here: its private copy is made with the author's own token once Slack's event lists it, so the
     * copy always belongs to a message and erasing the person's words finds it.
     */
    private function keepCopy(SlackChannel $slackChannel, string $slackId, string $name, string $mime, int $size, UploadedFile $file): void
    {
        if ($slackChannel->direct_type) {
            return;
        }
        $slackFile = SlackFile::updateOrCreate(['slack_id' => $slackId, 'is_private' => false], [
            'group_id'  => $slackChannel->group_id,
            'name'      => $name,
            'mime_type' => $mime,
            'size'      => $size,
            'mode'      => 'hosted',
            'state'     => SlackFileStateEnum::COPYING,
            'data'      => ['id' => $slackId, 'name' => $name, 'mimetype' => $mime, 'size' => $size, 'mode' => 'hosted'],
        ]);
        try {
            CopySlackMessageFile::make()->keepFromPath($slackFile, $file->getRealPath());
        } catch (Throwable $exception) {
            $slackFile->update(['state' => SlackFileStateEnum::FAILED, 'reason' => 'own copy: '.Str::limit($exception->getMessage(), 200)]);
            report($exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        return $this->handle($request->user(), $slackChannel, $request->file('file'));
    }
}
