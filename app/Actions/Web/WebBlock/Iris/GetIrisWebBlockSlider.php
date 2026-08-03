<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockSlider
{
    use AsObject;
    use WithIrisImageVariants;

    public const int CARD_IMAGE_SIZE = 320;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $cards = Arr::get($webBlock, 'web_block.layout.data.fieldValue.slider_data.cards', []);

        foreach ($cards as $index => $card) {
            $media = $this->findMediaFromImgProxyUrl(Arr::get($card, 'image.source.original'));

            if (!$media) {
                continue;
            }

            data_set(
                $webBlock,
                "web_block.layout.data.fieldValue.slider_data.cards.$index.image.source",
                GetPictureSources::run($media->getImage()->resize(self::CARD_IMAGE_SIZE, self::CARD_IMAGE_SIZE))
            );
        }

        return $webBlock;
    }
}
