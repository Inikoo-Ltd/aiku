<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 18 Jan 2025 01:21:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\UI;

use App\Actions\Helpers\Media\SaveModelLogo;
use App\Models\Helpers\Brand;
use App\Models\Web\Website;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

trait WithLogo
{
    public function processWebsiteLogo(array $modelData, Website|Brand $model): Website|Brand
    {
        if (Arr::has($modelData, 'image')) {
            /** @var UploadedFile $image */
            $image = Arr::pull($modelData, 'image');
            $imageData = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
            ];
            $model     = SaveModelLogo::run(
                model: $model,
                imageData: $imageData,
                scope: 'logo'
            );
        }
        return $model;
    }
}
