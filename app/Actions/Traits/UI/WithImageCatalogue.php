<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Traits\UI;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\ModelHasContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

trait WithImageCatalogue
{
    public function processCatalogueImage(array $modelData, ProductCategory|Collection|ModelHasContent $model): ProductCategory|Collection|ModelHasContent
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
                scope: 'catalogue'
            );
        }
        return $model;
    }
}
