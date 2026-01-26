<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Clocking\Traits;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithUpdateModelImage;
use App\Models\HumanResources\Clocking;
use Lorisleiva\Actions\Concerns\AsAction;

class SetClockingPhotoFromImage
{
    use AsAction;
    use WithActionUpdate;
    use WithUpdateModelImage;


    /**
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
     */
    public function handle(Clocking $clocking, string $imagePath, string $originalFilename, ?string $extension = null): Clocking
    {
        return $this->updateModelImage(
            model: $clocking,
            collection: 'photo',
            field: 'photo_id',
            imagePath: $imagePath,
            originalFilename: $originalFilename,
            extension: $extension
        );
    }
}
