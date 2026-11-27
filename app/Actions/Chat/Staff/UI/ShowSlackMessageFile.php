<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\UI;

use App\Actions\Chat\Staff\Slack\CopySlackMessageFile;
use App\Actions\Chat\Staff\Slack\GetSlackMessageFiles;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Enums\CRM\Livechat\SlackFileStateEnum;
use App\Helpers\ImgProxy\Image;
use App\Models\Chat\SlackFile;
use App\Models\Chat\SlackMessage;
use App\Models\Helpers\Media;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves aiku's copy of a Slack file to one person. The file is always reached through a message: the message's
 * channel must be readable by the person (SlackChannel::isReadableBy: public channels by visibleTo, private channels and DMs
 * by their members only, no role bypass) and the file must be listed in that message,
 * because one file can be shared in several channels and slack_files only remembers the first message. Every
 * refusal is a 404, so the route never says whether a message or a file exists.
 * A copy in the media library is streamed: a plain image is shown, a PDF opens in the browser's own PDF viewer (which
 * cannot take the page's sandbox policy) and anything else is downloaded;
 * a copy on the large files disk is answered with a redirect to a signed link made at that moment.
 * A private copy (INI-441) is only ever the private side's own row, streamed through aiku as a download with no-store, never
 * a signed link and never a thumbnail.
 */
class ShowSlackMessageFile
{
    use AsAction;

    public const int SIGNED_LINK_MINUTES = 1;

    public const int THUMBNAIL_PIXELS = 360;

    public const string PDF = 'application/pdf';

    public const array SAFE_HEADERS = [
        'X-Content-Type-Options'  => 'nosniff',
        'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        'Referrer-Policy'         => 'no-referrer',
    ];

    public function handle(SlackFile $slackFile, bool $asThumbnail = false): Response
    {
        if ($slackFile->is_private) {
            abort_if($asThumbnail || !GetSlackMessageFiles::hasCopy($slackFile), 404);
            $disk = Storage::disk($slackFile->disk);
            abort_unless($disk->exists($slackFile->path), 404);

            return $disk->response($slackFile->path, null, self::SAFE_HEADERS + [
                'Content-Type'        => 'application/octet-stream',
                'Content-Disposition' => $this->disposition('attachment', $slackFile),
                'Cache-Control'       => 'no-store',
            ]);
        }
        if ($slackFile->media_id === null) {
            abort_if($asThumbnail || !GetSlackMessageFiles::hasCopy($slackFile) || !CopySlackMessageFile::hasLargeDisk(), 404);

            return redirect()->away(
                $slackFile->temporaryUrl(self::SIGNED_LINK_MINUTES, [
                    'ResponseContentDisposition' => $this->disposition('attachment', $slackFile),
                    'ResponseContentType'        => 'application/octet-stream',
                ]),
                302,
                ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']
            );
        }

        $media = $slackFile->copy;
        abort_unless($media, 404);

        $isImage = in_array($media->mime_type, CopySlackMessageFile::IMAGE_MIME_TYPES, true)
            && in_array($slackFile->mime_type, CopySlackMessageFile::IMAGE_MIME_TYPES, true);

        $isPdf = $media->mime_type === self::PDF && $slackFile->mime_type === self::PDF;

        if ($asThumbnail) {
            abort_unless($isImage, 404);
            return $this->thumbnail($media) ?? abort(404);
        }

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ($isPdf ? Arr::except(self::SAFE_HEADERS, 'Content-Security-Policy') : self::SAFE_HEADERS) + [
            'Content-Type'        => $isImage || $isPdf ? $media->mime_type : 'application/octet-stream',
            'Content-Disposition' => $this->disposition($isImage || $isPdf ? 'inline' : 'attachment', $slackFile),
            'Cache-Control'       => 'private, max-age=86400',
        ]);
    }

    public function asController(SlackMessage $slackMessage, string $slackFile, ActionRequest $request): Response
    {
        $channel = $slackMessage->channel;
        abort_unless($channel && $request->user() && $channel->isReadableBy($request->user()), 404);

        $files = is_array($slackMessage->data['files'] ?? null) ? $slackMessage->data['files'] : [];
        abort_unless(collect($files)->contains(fn ($file) => is_array($file) && ($file['id'] ?? null) === $slackFile), 404);

        $copy = SlackFile::where('slack_id', $slackFile)
            ->where('is_private', !$channel->is_public)
            ->where('group_id', $channel->group_id)
            ->where('state', SlackFileStateEnum::COPIED)
            ->first();
        abort_unless($copy && GetSlackMessageFiles::hasCopy($copy), 404);

        return $this->handle($copy, $request->query('variant') === 'thumbnail');
    }

    /**
     * A small version from the image resizer, fetched by the server so its permanent address never reaches a browser.
     * Null when there is no resizer or it did not answer: the copy itself is served then.
     */
    private function thumbnail(Media $media): ?Response
    {
        $baseUrl = config('img-proxy.base_url');
        if (!$baseUrl) {
            return null;
        }

        $image = new Image()->make($media->getImgProxyFilename(), $media->is_animated)->resize(self::THUMBNAIL_PIXELS, self::THUMBNAIL_PIXELS);
        $url   = GetImgProxyUrl::run($media->is_animated ? $image : $image->extension('webp'));
        if ($internalUrl = config('img-proxy.internal_url')) {
            $url = Str::replaceStart($baseUrl, $internalUrl, $url);
        }

        try {
            $resized = Http::connectTimeout(2)->timeout(4)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        $type = strtolower(trim(Str::before((string) $resized->header('Content-Type'), ';')));
        if (!$resized->successful() || !in_array($type, CopySlackMessageFile::IMAGE_MIME_TYPES, true)) {
            return null;
        }

        return response($resized->body(), 200, self::SAFE_HEADERS + [
            'Content-Type'        => $type,
            'Content-Disposition' => 'inline',
            'Cache-Control'       => 'private, max-age=604800, immutable',
        ]);
    }

    /**
     * The name comes from whoever posted the file: slashes, control characters and quotes never reach the header.
     */
    private function disposition(string $disposition, SlackFile $slackFile): string
    {
        $name = trim((string) preg_replace('/[\/\\\\"\x00-\x1f\x7f]+/u', '_', (string) $slackFile->name), " .\t");
        $name = $name !== '' ? Str::limit($name, 200, '') : $slackFile->slack_id;

        $fallback = trim(str_replace(['%', '/', '\\'], '', Str::ascii($name)));

        return HeaderUtils::makeDisposition($disposition, $name, $fallback !== '' ? $fallback : 'file');
    }
}
