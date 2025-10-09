<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Oct 2023 15:23:45 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Helpers\Media\Hydrators\MediaHydrateDimensions;
use App\Actions\Helpers\Media\UpdateIsAnimatedMedia;
use App\Models\Comms\EmailTemplate;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Media;
use App\Models\HumanResources\Clocking;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Illuminate\Support\Str;

trait WithUpdateModelImage
{
    /**
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
     */
    public function updateModelImage(
        Website|Organisation|User|EmailTemplate|WebUser|Clocking $model,
        string $collection,
        string $field,
        string $imagePath,
        string $originalFilename,
        string $extension = null,
        array $properties = []
    ): Website|Organisation|User|EmailTemplate|WebUser|Clocking {
        $checksum = md5_file($imagePath);


        $extension = $extension ?? Str::afterLast($imagePath, '.');

        if ($model->getMedia($collection, ['checksum' => $checksum])->count() == 0) {
            $model->update([$field => null]);

            /** @var Media $media */
            $media = $model->addMedia($imagePath)
                ->preservingOriginal()
                ->withProperties(
                    array_merge(
                        [
                            'checksum' => $checksum,
                            'group_id' => group()->id,
                            'ulid'     => Str::ulid()
                        ],
                        $properties
                    )
                )
                ->usingName($originalFilename)
                ->usingFileName(hash('crc32b', $checksum).'.'.$extension)
                ->toMediaCollection($collection);
            $media->refresh();
            UpdateIsAnimatedMedia::run($media, $imagePath);
            MediaHydrateDimensions::run($media);
            $model->update([$field => $media->id]);
        }


        return $model;
    }
}
