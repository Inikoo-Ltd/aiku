<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockCta
{
    use AsObject;
    use WithIrisImageVariants;

    public const int IMAGE_SIZE = 1024;

    public function handle(array $webBlock): array
    {
        $source = Arr::get($webBlock, 'web_block.layout.data.fieldValue.image.source');
        if (!is_array($source)) {
            return $webBlock;
        }

        $media = $this->findMediaFromImgProxyUrl(Arr::get($source, 'original'));
        if (!$media) {
            return $webBlock;
        }

        data_set(
            $webBlock,
            'web_block.layout.data.fieldValue.image.source',
            GetPictureSources::run($media->getImage()->resize(self::IMAGE_SIZE, self::IMAGE_SIZE))
        );

        return $webBlock;
    }
}
