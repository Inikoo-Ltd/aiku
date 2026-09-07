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
     * Wix imports product media by URL, so the image only has to be publicly reachable.
     */
    public function handle(Media $media): ?string
    {
        $image = $media->getImage();

        if (!$image) {
            return null;
        }

        return GetImgProxyUrl::run($image->resize(1000, 1000));
    }
}
