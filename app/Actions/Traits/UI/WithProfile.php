<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 18 Jan 2025 01:21:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\UI;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

trait WithProfile
{
    public function processProfileAvatar(array $modelData, User|WebUser $model): User|WebUser
    {
        if (Arr::has($modelData, 'image')) {
            /** @var UploadedFile $image */
            $image = Arr::get($modelData, 'image');
            data_forget($modelData, 'image');
            $imageData = [
                'path' => $image->getPathName(),
                'originalName' => $image->getClientOriginalName(),
                'extension' => $image->getClientOriginalExtension(),
            ];
            $model = SaveModelImage::run(
                model: $model,
                imageData: $imageData,
                scope: 'avatar'
            );
        }

        return $model;
    }
}
