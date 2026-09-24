<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\RetinaAction;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\Concerns\AsAction;

class UploadProductImageToWix extends RetinaAction
{
    use AsAction;

    /**
     * Wix imports product media by fetching the URL itself, and the V1 catalogue does that
     * asynchronously without reporting back, so a URL it cannot read as an image simply leaves
     * the product with no pictures. The extension keeps the URL recognisable as one.
     */
    public function handle(Media $media): ?string
    {
        $image = $media->getImage();

        if (!$image) {
            return null;
        }

        return GetImgProxyUrl::run($image->extension('jpg')->resize(1000, 1000));
    }
}
