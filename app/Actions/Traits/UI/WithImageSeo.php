<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 28-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Traits\UI;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Models\Web\Webpage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

trait WithImageSeo
{
    public function processSeo(array $modelData, Webpage $model): Webpage
    {
        if (Arr::has($modelData, 'image')) {
            /** @var UploadedFile $image */
            $image = Arr::pull($modelData, 'image');
            $imageData = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
            ];
            $model     = SaveModelImage::run(
                model: $model,
                imageData: $imageData,
                scope: 'seo',
                foreignkeyMedia: 'seo_image_id'
            );
        }
        return $model;
    }
}
