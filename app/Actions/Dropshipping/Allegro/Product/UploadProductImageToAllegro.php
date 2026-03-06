<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Product;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UploadProductImageToAllegro extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(AllegroUser $allegroUser, Media $media)
    {
        if(! app()->isLocal()) {
            $imageUrl = GetImgProxyUrl::run($media->getImage()
                ->resize(480, 480));
        } else {
            $imageUrl = 'https://assets.allegrostatic.com/opbox/allegro.pl/homepage/Main%20Page/6lJEwSSohvBIIWNlJUU9sx-w1200-h1200.png';
        }

        return $allegroUser->uploadOfferImage($imageUrl);
    }
}
