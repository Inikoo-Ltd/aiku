<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\OrgAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;

class UploadImageMasterProductCategory extends OrgAction
{
    use WithAttachMediaToModel;
    use WithMasterProductCategoryAction;

    public function handle(MasterProductCategory $masterProductCategory, array $modelData): MasterProductCategory
    {
        /** @var \Illuminate\Http\UploadedFile $imageFile */
        $imageFile = Arr::get($modelData, 'image');

        $imageData = [
            'path'         => $imageFile->getPathName(),
            'originalName' => $imageFile->getClientOriginalName(),
            'extension'    => $imageFile->guessClientExtension(),
            'checksum'     => md5_file($imageFile->getPathName())
        ];

        $media = StoreMediaFromFile::run($masterProductCategory, $imageData, 'image');
        $this->attachMediaToModel($masterProductCategory, $media, 'image');

        UpdateMasterProductCategory::make()->action($masterProductCategory, [
            'image_id' => $media->id
        ]);

        return $masterProductCategory;
    }

    public function rules(): array
    {
        $rules = [
            'image' => ['sometimes', 'mimes:jpg,jpeg,png']
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }


}
