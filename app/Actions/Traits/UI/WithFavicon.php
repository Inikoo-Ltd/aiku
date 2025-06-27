<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 18 Jan 2025 01:21:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\UI;

use App\Actions\Helpers\Media\SaveModelFavicon;
use App\Models\Web\Website;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

trait WithFavicon
{
    public function processWebsiteFavicon(array $modelData, Website $website): Website
    {
        if (Arr::has($modelData, 'favicon')) {
            /** @var UploadedFile $image */
            $image     = Arr::pull($modelData, 'favicon');
            $imageData = [
                'path'         => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension'    => $image->getClientOriginalExtension(),
            ];
            $website   = SaveModelFavicon::run(
                model: $website,
                imageData: $imageData,
                scope: 'favicon'
            );

            $key = config('iris.cache.website.prefix')."_$website->domain";
            Cache::forget($key);

        }

        return $website;
    }
}
