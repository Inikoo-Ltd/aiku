<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UploadProductImageToTiktok extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(TiktokUser $tiktokUser, Media $media, $useCase = 'MAIN_IMAGE')
    {
        $productData = [
            'data' => $media->getBase64Image(),
            'use_case' => $useCase
        ];

        return $tiktokUser->uploadProductImageToTiktok($productData);
    }
}
