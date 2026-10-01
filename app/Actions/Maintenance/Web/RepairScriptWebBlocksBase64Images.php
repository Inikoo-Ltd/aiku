<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 01 Oct 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\WebBlock;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairScriptWebBlocksBase64Images
{
    use AsAction;
    use WithAttachMediaToModel;

    private const string BASE64_IMAGE_PATTERN = '~data:image/[a-z0-9.+-]+;base64,([A-Za-z0-9+/=\s]++)(?=["\')&])~i';

    private const array EXTENSIONS_BY_MIME_TYPE = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    public function handle(WebBlock $webBlock, bool $apply = true, ?Command $command = null): int
    {
        $code = data_get($webBlock->layout, 'data.fieldValue.value');

        if (!is_string($code) || !str_contains($code, ';base64,')) {
            return 0;
        }

        $images = $this->findImages($code);

        if ($images === null) {
            $command?->error("Web block: $webBlock->id || Could not be scanned: ".preg_last_error_msg());

            return 0;
        }

        preg_match_all('~data:([a-z0-9.+/-]+);base64,~i', strtr($code, array_fill_keys(array_keys($images), '')), $untouchedDataUris);
        $untouchedTypes = collect($untouchedDataUris[1])->countBy()->map(fn (int $count, string $type) => "$type x$count")->implode(', ');

        $command?->line("Web block: $webBlock->id || Base64 images: ".count($images)." || Left untouched: ".($untouchedTypes ?: 'none'));
        foreach ($webBlock->webpages as $webpage) {
            $command?->line("  Shop: {$webpage->shop->slug} || Website: {$webpage->website->domain} || Webpage: $webpage->code || ".$webpage->getUrl(true));
        }

        if (!$apply || !$images) {
            return count($images);
        }

        $imageUrlsByChecksum = [];
        $replacements        = [];

        foreach ($images as $dataUri => [$imageContent, $extension]) {
            $checksum = md5($imageContent);

            $imageUrlsByChecksum[$checksum] ??= $this->uploadImage($webBlock, $imageContent, $extension, $checksum, count($imageUrlsByChecksum) + 1);

            $replacements[$dataUri] = $imageUrlsByChecksum[$checksum];
        }

        $layout = $webBlock->layout;
        data_set($layout, 'data.fieldValue.value', strtr($code, $replacements));
        $webBlock->update(['layout' => $layout]);

        foreach ($webBlock->webpages as $webpage) {
            $hasUnpublishedChanges = $webpage->is_dirty;

            UpdateWebpageContent::run($webpage);

            if ($webpage->state === WebpageStateEnum::LIVE && !$hasUnpublishedChanges) {
                PublishWebpage::make()->action($webpage, [
                    'comment' => 'base64 images in script moved to uploaded images',
                ]);
            } elseif ($webpage->state === WebpageStateEnum::LIVE) {
                $command?->warn("  Webpage $webpage->code has unpublished changes, publish it to take the uploaded images live");
            }
        }

        return count($replacements);
    }

    /**
     * @return array<string, array{0: string, 1: string}>|null image content and extension keyed by data URI
     */
    private function findImages(string $code): ?array
    {
        if (preg_match_all(self::BASE64_IMAGE_PATTERN, $code, $matches, PREG_SET_ORDER) === false) {
            return null;
        }

        $images = [];

        foreach ($matches as [$dataUri, $base64]) {
            $imageContent = base64_decode(preg_replace('/\s+/', '', $base64), true);
            $imageSize    = $imageContent ? @getimagesizefromstring($imageContent) : false;
            $extension    = $imageSize ? Arr::get(self::EXTENSIONS_BY_MIME_TYPE, $imageSize['mime']) : null;

            if ($extension) {
                $images[$dataUri] = [$imageContent, $extension];
            }
        }

        return $images;
    }

    private function uploadImage(WebBlock $webBlock, string $imageContent, string $extension, string $checksum, int $position): string
    {
        $path      = tempnam(sys_get_temp_dir(), 'web-block-image-').".$extension";
        file_put_contents($path, $imageContent);

        try {
            $media = StoreMediaFromFile::run($webBlock, [
                'path'         => $path,
                'originalName' => "web-block-$webBlock->id-image-$position.$extension",
                'extension'    => $extension,
                'checksum'     => $checksum,
            ], 'image');
        } finally {
            @unlink($path);
        }

        $this->attachMediaToModel($webBlock, $media, 'image');

        return GetImgProxyUrl::run($media->getImage());
    }

    public string $commandSignature = 'repair:script_web_blocks_base64_images {website?} {--apply-changes}';

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();

        $website = $command->argument('website') ? Website::where('slug', $command->argument('website'))->firstOrFail() : null;
        $apply   = (bool)$command->option('apply-changes');
        $total   = 0;

        WebBlock::query()
            ->whereHas('webBlockType', fn ($query) => $query->where('code', 'script'))
            ->whereRaw("layout::text ilike '%;base64,%'")
            ->when($website, fn ($query) => $query->whereHas('webpages', fn ($webpages) => $webpages->where('webpages.website_id', $website->id)))
            ->select('id')
            ->chunkById(10, function (Collection $webBlockIds) use ($command, $apply, &$total) {
                foreach ($webBlockIds as $webBlockId) {
                    $total += $this->handle(WebBlock::find($webBlockId->id), $apply, $command);
                }
            });

        $command->info($apply ? "Uploaded $total base64 images." : "Found $total base64 images. Run with --apply-changes to upload them.");
    }
}
