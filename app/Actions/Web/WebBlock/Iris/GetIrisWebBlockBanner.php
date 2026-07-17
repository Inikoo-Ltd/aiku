<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Jul 2026 12:24:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use App\Models\Helpers\Media;
use App\Models\Web\Banner;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockBanner
{
    use AsObject;
    use WithIrisImageVariants;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $fieldValue = Arr::get($webBlock, 'web_block.layout.data.fieldValue', []);

        $bannerIds = collect(Arr::get($fieldValue, 'banner_responsive', []))
            ->pluck('id')
            ->push(Arr::get($fieldValue, 'banner_id'))
            ->filter()
            ->unique()
            ->values();

        if ($bannerIds->isEmpty()) {
            return $webBlock;
        }

        $bannersData = Banner::where('website_id', $webpage->website_id)
            ->whereIn('id', $bannerIds)
            ->get()
            ->mapWithKeys(fn (Banner $banner) => [
                $banner->id => [
                    'id'              => $banner->id,
                    'slug'            => $banner->slug,
                    'type'            => $banner->type?->value,
                    'state'           => $banner->state,
                    'ratio'           => $banner->ratio,
                    'compiled_layout' => $this->onlyVisibleComponents($banner->compiled_layout),
                ],
            ])->all();

        data_set($webBlock, 'web_block.layout.data.fieldValue.banners_data', $bannersData);

        return $webBlock;
    }

    private function onlyVisibleComponents(?array $compiledLayout): ?array
    {
        if (!is_array($compiledLayout)) {
            return null;
        }

        $components = collect(Arr::get($compiledLayout, 'components', []))
            ->filter(fn ($component) => Arr::get($component, 'visibility') == true)
            ->map(fn ($component) => $this->addImageDimensions($component))
            ->values()
            ->all();

        return array_merge($compiledLayout, ['components' => $components]);
    }

    private const VIEW_MAX_WIDTHS = [
        'mobile'  => 720,
        'tablet'  => 1024,
        'desktop' => 1440,
    ];

    private const SRCSET_WIDTHS = [480, 768, 1024, 1440];

    private function addImageDimensions(array $component): array
    {
        foreach (['mobile', 'tablet', 'desktop'] as $view) {
            $originalUrl = Arr::get($component, "image.$view.source.original");
            if (!$originalUrl) {
                continue;
            }

            $media = $this->findMediaFromImgProxyUrl($originalUrl);
            if (!$media) {
                continue;
            }

            data_set($component, "image.$view.source", $this->resizedSources($media, self::VIEW_MAX_WIDTHS[$view]));
            data_set($component, "image.$view.srcset", $this->getWidthSrcSets($media, self::SRCSET_WIDTHS));

            $cacheKey = "media_image_dimensions:$media->id";
            $dimensions = Cache::get($cacheKey);

            if (!$dimensions) {
                $size = rescue(fn () => getimagesize($media->getPath()), null, false);
                if ($size) {
                    $dimensions = ['width' => $size[0], 'height' => $size[1]];
                    Cache::forever($cacheKey, $dimensions);
                }
            }

            if ($dimensions && $dimensions['width'] && $dimensions['height']) {
                data_set($component, "image.$view.width", $dimensions['width']);
                data_set($component, "image.$view.height", $dimensions['height']);
            }
        }

        return $component;
    }

    /**
     * @return array<string, string>
     */
    private function resizedSources(Media $media, int $maxWidth): array
    {
        $sources = [
            'original' => GetImgProxyUrl::run($media->getImage()->resize($maxWidth, $maxWidth)),
        ];

        if (in_array('avif', config('img-proxy.formats')) && !$media->is_animated) {
            $sources['avif'] = GetImgProxyUrl::run($media->getImage()->resize($maxWidth, $maxWidth)->extension('avif'));
        }
        if (in_array('webp', config('img-proxy.formats'))) {
            $sources['webp'] = GetImgProxyUrl::run($media->getImage()->resize($maxWidth, $maxWidth)->extension('webp'));
        }

        return $sources;
    }
}
