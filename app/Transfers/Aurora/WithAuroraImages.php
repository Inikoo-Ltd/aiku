<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait WithAuroraImages
{
    public function getModelImagesCollection($model, $id): Collection
    {
        return DB::connection('aurora')
            ->table('Image Subject Bridge')
            ->leftJoin('Image Dimension', 'Image Subject Image Key', '=', 'Image Key')
            ->where('Image Subject Object', $model)
            ->where('Image Subject Object Key', $id)
            ->orderByRaw("FIELD(`Image Subject Is Principal`, 'Yes','No')")
            ->get();
    }

    public function getModelMainImage($model, $id)
    {
        return DB::connection('aurora')
            ->table('Image Subject Bridge')
            ->leftJoin('Image Dimension', 'Image Subject Image Key', '=', 'Image Key')
            ->where('Image Subject Object', $model)
            ->where('Image Subject Object Key', $id)
            ->where('Image Subject Is Principal', 'Yes')
            ->first();
    }


    public function fetchImage($auroraImageData): array
    {
        $image_path = sprintf(
            config('app.aurora_image_path'),
            Arr::get($this->organisation->source, 'account_code')
        );

        $image_path .= preg_replace('/^img\/db/', '', $auroraImageData->{'Image Path'});

        if (file_exists($image_path)) {
            return [
                'image_path' => $image_path,
                'filename'   => $auroraImageData->{'Image Filename'},
                'mime'       => $auroraImageData->{'Image MIME Type'},

            ];
        } else {
            return [];
        }
    }
}
