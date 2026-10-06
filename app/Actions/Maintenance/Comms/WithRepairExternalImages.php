<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Oct 2026 15:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Maintenance\Comms;

use App\Actions\Comms\Email\UploadImagesToEmail;
use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Email;
use App\Models\Comms\EmailTemplate;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

trait WithRepairExternalImages
{
    use WithAttachMediaToModel;

    private const array IMAGE_KEYS = ['src', 'image', 'background-image', 'thumbSrc', 'iconSrc'];

    /**
     * @var array<string, string|null> external url => aiku url, null when the download failed
     */
    private array $aikuUrls = [];

    /**
     * @return array{msg: string, replaced: int, failed: array<int, string>}
     */
    protected function repairEmailSnapshots(Email $email, Mailshot|Outbox $repairedModel): array
    {
        $snapshots = collect([$email->liveSnapshot, $email->unpublishedSnapshot])->filter()->unique('id');
        if ($snapshots->isEmpty()) {
            return ['msg' => 'email has no snapshots', 'replaced' => 0, 'failed' => []];
        }

        $this->aikuUrls = [];

        foreach ($snapshots as $snapshot) {
            [$layout, $compiledLayout] = $this->repairLayout($email->shop ?? $email->group, $snapshot->layout, $snapshot->compiled_layout);

            $snapshot->updateQuietly([
                'layout'          => $layout,
                'checksum'        => md5(json_encode($layout)),
                'compiled_layout' => $compiledLayout,
            ]);
        }

        return $this->recordExternalImagesRepair($repairedModel);
    }

    /**
     * @param  array<mixed>  $layout
     *
     * @return array{0: array<mixed>, 1: string|null}
     */
    protected function repairLayout(Shop|Group $owner, array $layout, ?string $compiledLayout): array
    {
        array_walk_recursive($layout, function (&$value, $key) use ($owner) {
            if (!is_string($value) || !in_array($key, self::IMAGE_KEYS, true)) {
                return;
            }

            $url = preg_match('~^url\([\'"]?(.+?)[\'"]?\)$~', $value, $match) ? $match[1] : $value;
            if (!Str::startsWith($url, ['http://', 'https://']) || $this->isAikuUrl($url)) {
                return;
            }

            if (!array_key_exists($url, $this->aikuUrls)) {
                $this->aikuUrls[$url] = $this->reuploadToAiku($url, $owner);
            }

            if ($this->aikuUrls[$url]) {
                $value = str_replace($url, $this->aikuUrls[$url], $value);
            }
        });

        $replacements = array_filter($this->aikuUrls);

        return [
            $layout,
            $compiledLayout === null ? null : str_replace(array_keys($replacements), array_values($replacements), $compiledLayout),
        ];
    }

    /**
     * @return array{msg: string, replaced: int, failed: array<int, string>}
     */
    protected function recordExternalImagesRepair(Mailshot|Outbox|EmailTemplate $repairedModel): array
    {
        $replaced = count(array_filter($this->aikuUrls));
        $failed   = array_keys(array_diff_key($this->aikuUrls, array_filter($this->aikuUrls)));

        $repairedModel->updateQuietly([
            'data' => array_merge($repairedModel->data ?? [], [
                'external_images_repair' => [
                    'repaired_at' => now()->toIso8601String(),
                    'replaced'    => $replaced,
                    'failed'      => $failed,
                ],
            ]),
        ]);

        return ['msg' => "replaced $replaced, failed ".count($failed), 'replaced' => $replaced, 'failed' => $failed];
    }

    /**
     * @param  array<int, string>|null  $slugs
     */
    protected function repairExternalImagesFromCommand(Command $command, Builder $query, ?array $slugs, ?string $shopSlug): int
    {
        Nightwatch::dontSample();

        $query
            ->when(
                $slugs,
                fn (Builder $query) => $query->whereIn('slug', $slugs),
                fn (Builder $query) => $query->whereJsonDoesntContainKey('data->external_images_repair')
            )
            ->when($shopSlug, fn (Builder $query) => $query->where('shop_id', Shop::where('slug', $shopSlug)->firstOrFail()->id));

        $total   = $query->clone()->count();
        $current = 0;

        foreach ($query->lazyById() as $model) {
            $current++;
            $res = $this->handle($model);
            $command->line("[$current/$total] $model->slug: {$res['msg']}");
        }

        $command->info("Done: $current processed");

        return 0;
    }

    private function isAikuUrl(string $url): bool
    {
        return parse_url($url, PHP_URL_HOST) === parse_url((string) config('img-proxy.base_url'), PHP_URL_HOST);
    }

    private function reuploadToAiku(string $url, Shop|Group $owner): ?string
    {
        $cacheKey = 'repair-mailshot-external-image:'.md5($url);

        $media = Media::find(Cache::get($cacheKey)) ?? $this->downloadToMedia($url, $owner);
        if (!$media) {
            return null;
        }

        Cache::forever($cacheKey, $media->id);

        if (!$owner->images()->where('media.id', $media->id)->wherePivot('scope', UploadImagesToEmail::MEDIA_SCOPE)->exists()) {
            $this->attachMediaToModel($owner, $media, UploadImagesToEmail::MEDIA_SCOPE);
        }

        return Arr::get(GetPictureSources::run($media->getImage()), 'original');
    }

    private function downloadToMedia(string $url, Shop|Group $owner): ?Media
    {
        $path = tempnam(sys_get_temp_dir(), 'repair-external-image-');

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

            $checksum = md5_file($path);

            return Media::where('group_id', $owner instanceof Group ? $owner->id : $owner->group_id)
                ->where('collection_name', UploadImagesToEmail::MEDIA_SCOPE)
                ->where('checksum', $checksum)
                ->first()
                ?? StoreMediaFromFile::run(
                    $owner,
                    [
                        'path'         => $path,
                        'originalName' => urldecode(basename((string) parse_url($url, PHP_URL_PATH))),
                        'extension'    => Arr::first(MimeTypes::getDefault()->getExtensions($mimeType)),
                        'checksum'     => $checksum,
                    ],
                    UploadImagesToEmail::MEDIA_SCOPE
                );
        } catch (Throwable) {
            return null;
        } finally {
            File::delete($path);
        }
    }
}
