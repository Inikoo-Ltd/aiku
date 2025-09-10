<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:34:03 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media;

use App\Actions\Helpers\Media\Hydrators\MediaHydrateDimension;
use App\Models\Helpers\Media;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreMediaFromFile
{
    use AsAction;

    public function handle($model, $imageData, $collection, $type = 'image'): Media
    {
        $extension = Arr::get($imageData, 'extension');
        if (!$extension) {
            $extension = pathinfo($imageData['path'], PATHINFO_EXTENSION);
        }


        $media = $model->addMedia($imageData['path'])
            ->preservingOriginal()
            ->withProperties(
                array_merge(
                    [
                        'checksum' => $imageData['checksum'],
                        'group_id' => group()->id,
                        'type'     => $type,
                        'ulid'     => Str::ulid()

                    ],
                )
            )
            ->usingName($imageData['originalName'])
            ->usingFileName(hash('crc32b', $imageData['checksum']).'.'.$extension)
            ->toMediaCollection($collection);

        UpdateIsAnimatedMedia::run($media, $imageData['path']);
        MediaHydrateDimension::run($media); //Dimensions
        return $media;
    }
}
