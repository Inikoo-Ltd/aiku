<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Oct 2026 14:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Maintenance\Comms;

use App\Actions\Comms\Email\UploadImagesToEmail;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Email;
use App\Models\Comms\Mailshot;
use App\Models\Helpers\Media;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairMailshotSnapshotExternalImages
{
    use AsAction;
    use WithAttachMediaToModel;

    private const array IMAGE_KEYS = ['src', 'image', 'background-image', 'thumbSrc', 'iconSrc'];

    /**
     * @return array{msg: string, replaced: int, failed: array<int, string>}
     */
    public function handle(Mailshot $mailshot): array
    {
        $email = $mailshot->email;
        if (!$email) {
            return ['msg' => 'mailshot has no email', 'replaced' => 0, 'failed' => []];
        }

        $snapshots = collect([$email->liveSnapshot, $email->unpublishedSnapshot])->filter()->unique('id');
        if ($snapshots->isEmpty()) {
            return ['msg' => 'email has no snapshots', 'replaced' => 0, 'failed' => []];
        }

        $aikuUrls = [];

        foreach ($snapshots as $snapshot) {
            $layout = $snapshot->layout;

            array_walk_recursive($layout, function (&$value, $key) use ($email, &$aikuUrls) {
                if (!is_string($value) || !in_array($key, self::IMAGE_KEYS, true)) {
                    return;
                }

                $url = preg_match('~^url\([\'"]?(.+?)[\'"]?\)$~', $value, $match) ? $match[1] : $value;
                if (!Str::startsWith($url, ['http://', 'https://']) || $this->isAikuUrl($url)) {
                    return;
                }

                if (!array_key_exists($url, $aikuUrls)) {
                    $aikuUrls[$url] = $this->reuploadToAiku($url, $email);
                }

                if ($aikuUrls[$url]) {
                    $value = str_replace($url, $aikuUrls[$url], $value);
                }
            });

            $replacements = array_filter($aikuUrls);

            $snapshot->updateQuietly([
                'layout'          => $layout,
                'checksum'        => md5(json_encode($layout)),
                'compiled_layout' => $snapshot->compiled_layout === null
                    ? null
                    : str_replace(array_keys($replacements), array_values($replacements), $snapshot->compiled_layout),
            ]);
        }

        $replaced = count(array_filter($aikuUrls));
        $failed   = array_keys(array_diff_key($aikuUrls, array_filter($aikuUrls)));

        $mailshot->updateQuietly([
            'data' => array_merge($mailshot->data ?? [], [
                'external_images_repair' => [
                    'repaired_at' => now()->toIso8601String(),
                    'replaced'    => $replaced,
                    'failed'      => $failed,
                ],
            ]),
        ]);

        return ['msg' => "replaced $replaced, failed ".count($failed), 'replaced' => $replaced, 'failed' => $failed];
    }

    private function isAikuUrl(string $url): bool
    {
        return parse_url($url, PHP_URL_HOST) === parse_url((string) config('img-proxy.base_url'), PHP_URL_HOST);
    }

    private function reuploadToAiku(string $url, Email $email): ?string
    {
        $cacheKey = 'repair-mailshot-external-image:'.md5($url);

        $media = Media::find(Cache::get($cacheKey)) ?? $this->downloadToMedia($url, $email);
        if (!$media) {
            return null;
        }

        Cache::forever($cacheKey, $media->id);

        $shop = $email->shop;
        if (!$shop->images()->where('media.id', $media->id)->wherePivot('scope', UploadImagesToEmail::MEDIA_SCOPE)->exists()) {
            $this->attachMediaToModel($shop, $media, UploadImagesToEmail::MEDIA_SCOPE);
        }

        return Arr::get(GetPictureSources::run($media->getImage()), 'original');
    }

    private function downloadToMedia(string $url, Email $email): ?Media
    {
        $path = tempnam(sys_get_temp_dir(), 'mailshot-image-');

        try {
            $response = Http::timeout(30)->get($url);
            if (!$response->successful()) {
                return null;
            }

            file_put_contents($path, $response->body());
            $mimeType = (string) mime_content_type($path);
            if (!Str::startsWith($mimeType, 'image/')) {
                return null;
            }

            return Media::where('group_id', $email->group_id)
                ->where('collection_name', UploadImagesToEmail::MEDIA_SCOPE)
                ->where('checksum', md5_file($path))
                ->first()
                ?? UploadImagesToEmail::run($email, [
                    'images' => [new UploadedFile($path, urldecode(basename((string) parse_url($url, PHP_URL_PATH))), $mimeType, null, true)],
                ])->first();
        } catch (Throwable) {
            return null;
        } finally {
            File::delete($path);
        }
    }

    public string $commandSignature = 'repair:mailshot-snapshot-external-images {mailshots?* : mailshot slugs, re-runs even if already repaired} {--shop= : only mailshots of this shop slug}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $mailshotSlugs = $command->argument('mailshots');
        $shopSlug      = $command->option('shop');

        $query = Mailshot::whereIn('state', [MailshotStateEnum::SENT, MailshotStateEnum::SENDING])
            ->when(
                $mailshotSlugs,
                fn ($query) => $query->whereIn('slug', $mailshotSlugs),
                fn ($query) => $query->whereJsonDoesntContainKey('data->external_images_repair')
            )
            ->when($shopSlug, fn ($query) => $query->where('shop_id', Shop::where('slug', $shopSlug)->firstOrFail()->id));

        $total   = $query->clone()->count();
        $current = 0;

        foreach ($query->lazyById() as $mailshot) {
            $current++;
            $res = $this->handle($mailshot);
            $command->line("[$current/$total] $mailshot->slug: {$res['msg']}");
        }

        $command->info("Done: $current mailshots processed");

        return 0;
    }
}
