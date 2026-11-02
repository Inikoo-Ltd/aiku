<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 Oct 2026 20:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * A full width background banner: its saved AVIF is asked for at the banner quality, as
 * GetIrisWebBlockBanner does, instead of imgproxy's global 50.
 */
class GetIrisWebBlockCtaImageBackground
{
    use AsObject;
    use WithIrisImageVariants;

    public function handle(array $webBlock): array
    {
        $path   = 'web_block.layout.data.fieldValue.image.source';
        $source = Arr::get($webBlock, $path);
        if (!is_array($source)) {
            return $webBlock;
        }

        if (isset($source['original'])) {
            return data_set($webBlock, $path, $this->withAvifQuality($source));
        }

        foreach (['mobile', 'tablet', 'desktop'] as $view) {
            if (is_array($source[$view] ?? null)) {
                data_set($webBlock, "$path.$view", $this->withAvifQuality($source[$view]));
            }
        }

        return $webBlock;
    }

    private function withAvifQuality(array $source): array
    {
        if (!isset($source['avif'])) {
            return $source;
        }

        $media = $this->findMediaFromImgProxyUrl(Arr::get($source, 'original'));
        if ($media && !$media->is_animated) {
            $source['avif'] = GetImgProxyUrl::run($media->getImage()->extension('avif')->quality(GetIrisWebBlockBanner::AVIF_QUALITY));
        }

        return $source;
    }
}
