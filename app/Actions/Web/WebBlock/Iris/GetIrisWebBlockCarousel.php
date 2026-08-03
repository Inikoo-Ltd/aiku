<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockCarousel
{
    use AsObject;
    use WithIrisImageVariants;

    public const int CARD_IMAGE_SIZE = 720;

    public const array SRCSET_WIDTHS = [360, 720, 1440];

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $cards = Arr::get($webBlock, 'web_block.layout.data.fieldValue.carousel_data.cards', []);

        foreach ($cards as $index => $card) {
            $media = $this->findMediaFromImgProxyUrl(Arr::get($card, 'image.source.original'));

            if (!$media) {
                continue;
            }

            $image = $media->getImage()->resize(self::CARD_IMAGE_SIZE, self::CARD_IMAGE_SIZE);

            data_set(
                $webBlock,
                "web_block.layout.data.fieldValue.carousel_data.cards.$index.image.source",
                GetPictureSources::run($image)
            );

            data_set(
                $webBlock,
                "web_block.layout.data.fieldValue.carousel_data.cards.$index.image.srcset",
                $this->getWidthSrcSets($media, self::SRCSET_WIDTHS)
            );
        }

        return $webBlock;
    }
}
