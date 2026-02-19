<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\Helpers\Images\GetImgProxyUrl;
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
        try {
            $path = GetImgProxyUrl::run($media->getImage()
                ->resize(480, 480));

            $productData = [
                [
                    'name'     => 'data',
                    'contents' => fopen($path, 'r'),
                    'filename' => basename($path),
                    'headers'  => ['Content-Type' => mime_content_type($path)],
                ],
                [
                    'name'     => 'use_case',
                    'contents' => $useCase,
                ],
            ];
        } catch (\Exception $e) {
            $fallbackUrl = "https://sf-static.tiktokcdn.com/obj/eden-sg/uhtyvueh7nulogpoguhm/tiktok-icon2.png";
            $tempPath = tempnam(sys_get_temp_dir(), 'tiktok_') . '.png';
            file_put_contents($tempPath, file_get_contents($fallbackUrl));

            $productData = [
                [
                    'name'     => 'data',
                    'contents' => fopen($tempPath, 'r'),
                    'filename' => basename($tempPath),
                    'headers'  => ['Content-Type' => 'image/png'],
                ],
                [
                    'name'     => 'use_case',
                    'contents' => $useCase,
                ],
            ];
        }

        return $tiktokUser->uploadProductImageToTiktok($productData);
    }
}
