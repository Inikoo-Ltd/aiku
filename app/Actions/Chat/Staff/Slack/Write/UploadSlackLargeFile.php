<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\CopySlackMessageFile;
use App\Enums\CRM\Livechat\SlackFileStateEnum;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackFile;
use App\Models\SysAdmin\User;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * A file too big for UploadSlackFile (Octane stops a request at 45 s, Cloudflare at 100 MB), up to 1 GB: aiku starts a
 * multipart upload on the private R2 bucket and signs one short-lived link per part, the browser sends the parts straight
 * to R2, and once it reports them aiku checks the real size, then a queued job checks the real type and relays the bytes
 * from R2 to Slack as the person. The job leaves the file where UploadSlackFile would, so SendSlackFiles completes it the
 * same way. A channel file keeps its R2 object as our copy; a DM file's object is deleted (its private copy comes later
 * with the author's token). Parts never completed are dropped by R2's own expiry of unfinished multipart uploads.
 */
class UploadSlackLargeFile
{
    use AsAction;
    use WithSlackMessageWrite;

    public const int PART_BYTES = 50 * 1024 * 1024;

    public const int SIGNED_PART_MINUTES = 120;

    public const int STATE_SECONDS = 3 * 3600;

    public const int SNIFF_BYTES = 64 * 1024;

    public string $jobQueue = 'default';

    public int $jobTries = 1;

    public int $jobTimeout = 1800;

    protected function writesDirect(): bool
    {
        return true;
    }

    public static function maxBytes(): ?int
    {
        return CopySlackMessageFile::hasLargeDisk() ? CopySlackMessageFile::MAX_LARGE_BYTES : null;
    }

    public static function stateKey(string $upload): string
    {
        return 'slack-large-upload:'.$upload;
    }

    /**
     * @return array{upload: string, part_bytes: int, urls: list<string>}
     */
    public function start(User $user, SlackChannel $slackChannel, string $name, int $size, string $mimeType): array
    {
        $this->ensureWritable($user, $slackChannel);
        $max = self::maxBytes();
        if (!$max) {
            $this->refuse(__('Large files cannot be sent from aiku yet.'));
        }
        if ($size > $max) {
            $this->refuse(__('This file is larger than the :size aiku can send to Slack.', ['size' => '1 GB']));
        }
        if (in_array($mimeType, UploadSlackFile::REFUSED_TYPES, true)) {
            $this->refuse(__('Slack files of this type cannot be sent from aiku.'));
        }
        $this->clientFor($user, channel: $slackChannel);

        $upload = strtolower((string) Str::ulid());
        $path   = 'slack-uploads/'.$upload;
        $s3     = $this->s3();
        $bucket = $this->bucket();
        $id     = (string) $s3->createMultipartUpload(['Bucket' => $bucket, 'Key' => $path])['UploadId'];

        $urls = [];
        for ($part = 1; $part <= (int) ceil($size / self::PART_BYTES); $part++) {
            $command = $s3->getCommand('UploadPart', ['Bucket' => $bucket, 'Key' => $path, 'UploadId' => $id, 'PartNumber' => $part]);
            $urls[]  = (string) $s3->createPresignedRequest($command, '+'.self::SIGNED_PART_MINUTES.' minutes')->getUri();
        }

        Cache::put(self::stateKey($upload), [
            'user_id'    => $user->id,
            'channel_id' => $slackChannel->id,
            'upload_id'  => $id,
            'path'       => $path,
            'name'       => Str::limit(trim(basename(str_replace('\\', '/', $name))) ?: 'file', 200, ''),
            'size'       => $size,
            'parts'      => count($urls),
            'state'      => 'uploading',
            'file_id'    => null,
            'error'      => null,
        ], self::STATE_SECONDS);

        return ['upload' => $upload, 'part_bytes' => self::PART_BYTES, 'urls' => $urls];
    }

    /**
     * @param  list<array{part: int, etag: string}>  $parts
     * @return array{state: string, file_id: string|null, error: string|null}
     */
    public function complete(User $user, SlackChannel $slackChannel, string $upload, array $parts): array
    {
        $state = $this->stateFor($user, $slackChannel, $upload);
        if ($state['state'] !== 'uploading') {
            return self::publicState($state);
        }
        if (count($parts) !== $state['parts']) {
            $this->refuse(__('Some parts of the file did not arrive. Try again.'));
        }
        if (!Cache::add(self::stateKey($upload).':completing', true, self::STATE_SECONDS)) {
            return self::publicState($state);
        }

        $s3 = $this->s3();
        try {
            $s3->completeMultipartUpload([
                'Bucket'          => $this->bucket(),
                'Key'             => $state['path'],
                'UploadId'        => $state['upload_id'],
                'MultipartUpload' => ['Parts' => collect($parts)->sortBy('part')->map(fn (array $part) => ['PartNumber' => (int) $part['part'], 'ETag' => $part['etag']])->values()->all()],
            ]);
            $size = (int) $s3->headObject(['Bucket' => $this->bucket(), 'Key' => $state['path']])['ContentLength'];
        } catch (Throwable $exception) {
            report($exception);
            $this->discard($state);
            $this->remember($upload, ['state' => 'failed', 'error' => __('The file did not arrive complete. Try again.')]);
            $this->refuse(__('The file did not arrive complete. Try again.'));
        }

        if ($size !== $state['size'] || $size > CopySlackMessageFile::MAX_LARGE_BYTES) {
            $this->discard($state);
            $this->remember($upload, ['state' => 'failed', 'error' => __('The file did not arrive complete. Try again.')]);
            $this->refuse(__('The file did not arrive complete. Try again.'));
        }

        $state = $this->remember($upload, ['state' => 'relaying']);
        self::dispatch($upload);

        return self::publicState($state);
    }

    /**
     * @return array{state: string, file_id: string|null, error: string|null}
     */
    public function status(User $user, SlackChannel $slackChannel, string $upload): array
    {
        return self::publicState($this->stateFor($user, $slackChannel, $upload));
    }

    public function asJob(string $upload): void
    {
        $state = Cache::get(self::stateKey($upload));
        if (!$state || $state['state'] !== 'relaying') {
            return;
        }
        $disk = Storage::disk(CopySlackMessageFile::LARGE_DISK);

        try {
            $user         = User::findOrFail($state['user_id']);
            $slackChannel = SlackChannel::findOrFail($state['channel_id']);
            setPermissionsTeamId($user->group_id);
            $this->ensureWritable($user, $slackChannel);

            $mime = $this->sniff($state['path']);
            if (!UploadSlackFile::isAllowedType($mime)) {
                $this->refuse(__('Slack files of this type cannot be sent from aiku.'));
            }

            $client = $this->clientFor($user, channel: $slackChannel);
            $slack  = $this->callSlack($client, 'files.getUploadURLExternal', ['filename' => $state['name'], 'length' => $state['size']]);
            $url    = (string) ($slack['upload_url'] ?? '');
            $fileId = (string) ($slack['file_id'] ?? '');
            if ($fileId === '' || !str_ends_with((string) parse_url($url, PHP_URL_HOST), '.slack.com') || parse_url($url, PHP_URL_SCHEME) !== 'https') {
                $this->refuse(self::sentenceFor(null));
            }

            $this->relay($state['path'], $url, $mime, $state['size']);
            $this->keep($slackChannel, $fileId, $state, $mime);
            Cache::put(UploadSlackFile::cacheKey($user, $slackChannel, $fileId), $state['name'], UploadSlackFile::PENDING_SECONDS);
            $this->remember($upload, ['state' => 'done', 'file_id' => $fileId]);
        } catch (Throwable $exception) {
            $disk->delete($state['path']);
            if (!$exception instanceof ValidationException) {
                report($exception);
            }
            $this->remember($upload, ['state' => 'failed', 'error' => $exception instanceof ValidationException ? $exception->getMessage() : __('Slack did not accept this file. Try again.')]);
        }
    }

    public function jobFailed(Throwable $exception, string $upload): void
    {
        $state = Cache::get(self::stateKey($upload));
        if (!$state || $state['state'] !== 'relaying') {
            return;
        }
        Storage::disk(CopySlackMessageFile::LARGE_DISK)->delete($state['path']);
        $this->remember($upload, ['state' => 'failed', 'error' => __('Slack did not accept this file. Try again.')]);
    }

    private function sniff(string $path): string
    {
        $stream = Storage::disk(CopySlackMessageFile::LARGE_DISK)->readStream($path);
        try {
            $head = (string) stream_get_contents($stream, self::SNIFF_BYTES);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($head);
    }

    private function relay(string $path, string $url, string $mime, int $size): void
    {
        $stream = Storage::disk(CopySlackMessageFile::LARGE_DISK)->readStream($path);
        try {
            $response = Http::timeout($this->jobTimeout - 60)->withBody(Utils::streamFor($stream, ['size' => $size]), $mime)->post($url);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if ($response->failed()) {
            $this->refuse(__('Slack did not accept this file. Try again.'));
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function keep(SlackChannel $slackChannel, string $fileId, array $state, string $mime): void
    {
        if ($slackChannel->direct_type) {
            Storage::disk(CopySlackMessageFile::LARGE_DISK)->delete($state['path']);

            return;
        }
        $kept = 'slack-files/'.$fileId.'/'.Str::slug(pathinfo($state['name'], PATHINFO_FILENAME) ?: 'file').(($extension = pathinfo($state['name'], PATHINFO_EXTENSION)) ? '.'.strtolower($extension) : '');
        if ($state['size'] > CopySlackMessageFile::mediaLimit()) {
            Storage::disk(CopySlackMessageFile::LARGE_DISK)->move($state['path'], $kept);
            $state['path'] = $kept;
        }
        $slackFile = SlackFile::updateOrCreate(['slack_id' => $fileId, 'is_private' => false], [
            'group_id'  => $slackChannel->group_id,
            'name'      => $state['name'],
            'mime_type' => $mime,
            'size'      => $state['size'],
            'mode'      => 'hosted',
            'state'     => SlackFileStateEnum::COPIED,
            'disk'      => CopySlackMessageFile::LARGE_DISK,
            'path'      => $state['path'],
            'data'      => ['id' => $fileId, 'name' => $state['name'], 'mimetype' => $mime, 'size' => $state['size'], 'mode' => 'hosted'],
        ]);
        if ($state['size'] <= CopySlackMessageFile::mediaLimit()) {
            $this->moveToMedia($slackFile, $state['path']);
        }
    }

    /**
     * Copies up to the media library's limit live there like every other Slack file, with their thumbnail.
     */
    private function moveToMedia(SlackFile $slackFile, string $path): void
    {
        $disk      = Storage::disk(CopySlackMessageFile::LARGE_DISK);
        $temporary = (string) tempnam(sys_get_temp_dir(), 'slack-large-');
        try {
            $source = $disk->readStream($path);
            $target = fopen($temporary, 'w');
            $copied = stream_copy_to_stream($source, $target);
            fclose($target);
            fclose($source);
            if ($copied !== $slackFile->size) {
                throw new \RuntimeException('short read from R2: '.$copied.' of '.$slackFile->size);
            }
            CopySlackMessageFile::make()->keepFromPath($slackFile, $temporary);
            $slackFile->update(['disk' => null, 'path' => null]);
            $disk->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function discard(array $state): void
    {
        try {
            $this->s3()->abortMultipartUpload(['Bucket' => $this->bucket(), 'Key' => $state['path'], 'UploadId' => $state['upload_id']]);
        } catch (Throwable) {
        }
        Storage::disk(CopySlackMessageFile::LARGE_DISK)->delete($state['path']);
    }

    private function ensureWritable(User $user, SlackChannel $slackChannel): void
    {
        $this->ensureMayWrite($user, $slackChannel);
        if (!$slackChannel->direct_type && !SlackChannel::kept()->whereKey($slackChannel->id)->exists()) {
            $this->refuse(__('Files cannot be sent to this channel.'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function stateFor(User $user, SlackChannel $slackChannel, string $upload): array
    {
        $state = Cache::get(self::stateKey($upload));
        abort_unless($state && $state['user_id'] === $user->id && $state['channel_id'] === $slackChannel->id, 404);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function remember(string $upload, array $changes): array
    {
        $state = array_merge((array) Cache::get(self::stateKey($upload)), $changes);
        Cache::put(self::stateKey($upload), $state, self::STATE_SECONDS);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{state: string, file_id: string|null, error: string|null}
     */
    private static function publicState(array $state): array
    {
        return ['state' => $state['state'], 'file_id' => $state['file_id'], 'error' => $state['error']];
    }

    private function s3(): \Aws\S3\S3Client
    {
        return Storage::disk(CopySlackMessageFile::LARGE_DISK)->getClient();
    }

    private function bucket(): string
    {
        return (string) config('filesystems.disks.'.CopySlackMessageFile::LARGE_DISK.'.bucket');
    }

    /**
     * @return array{upload: string, part_bytes: int, urls: list<string>}
     */
    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        $data = Validator::make($request->all(), [
            'name'      => ['required', 'string', 'max:255'],
            'size'      => ['required', 'integer', 'min:1'],
            'mime_type' => ['required', 'string', 'max:255'],
        ])->validate();

        return $this->start($request->user(), $slackChannel, $data['name'], (int) $data['size'], $data['mime_type']);
    }

    /**
     * @return array{state: string, file_id: string|null, error: string|null}
     */
    public function inComplete(SlackChannel $slackChannel, string $upload, ActionRequest $request): array
    {
        $data = Validator::make($request->all(), [
            'parts'        => ['required', 'array', 'min:1', 'max:'.(int) ceil(CopySlackMessageFile::MAX_LARGE_BYTES / self::PART_BYTES)],
            'parts.*.part' => ['required', 'integer', 'min:1'],
            'parts.*.etag' => ['required', 'string', 'max:128'],
        ])->validate();

        return $this->complete($request->user(), $slackChannel, $upload, $data['parts']);
    }

    /**
     * @return array{state: string, file_id: string|null, error: string|null}
     */
    public function inStatus(SlackChannel $slackChannel, string $upload, ActionRequest $request): array
    {
        return $this->status($request->user(), $slackChannel, $upload);
    }
}
