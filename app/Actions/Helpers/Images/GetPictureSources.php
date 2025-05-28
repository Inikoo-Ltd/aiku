<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Aug 2023 12:47:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Images;

use App\Helpers\ImgProxy\Image;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetPictureSources
{
    use AsAction;


    public function handle(Image $image): array
    {
        $sources = [
            'original' => GetImgProxyUrl::run($image)
        ];


        if (in_array('avif', config('img-proxy.formats')) and !$image->is_animated) {
            $sources['avif'] = GetImgProxyUrl::run($image->extension('avif'));
        }

        if (in_array('webp', config('img-proxy.formats'))) {
            $sources['webp'] = GetImgProxyUrl::run($image->extension('webp'));
        }

        if (in_array('png', config('img-proxy.formats'))) {
            $sources['png'] = GetImgProxyUrl::run($image->extension('png'));
        }


        if ($image->getWidth() or $image->getHeight()) {
            $image_2x = $image->resize(
                ($image->getWidth() ?? 0)  * 2,
                ($image->getHeight() ?? 0) * 2,
            );

            if (Arr::has($sources, 'avif')) {
                $sources['avif_2x']     = GetImgProxyUrl::run($image_2x->extension('avif'));
            }

            if (Arr::has($sources, 'webp')) {
                $sources['webp_2x']     = GetImgProxyUrl::run($image_2x->extension('webp'));
            }

            if (Arr::has($sources, 'png')) {
                $sources['png_2x']     = GetImgProxyUrl::run($image_2x->extension('png'));
            }

            $sources['original_2x'] = GetImgProxyUrl::run($image_2x->extension(null));
        }

        return $sources;
    }

}
