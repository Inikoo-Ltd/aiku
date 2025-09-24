<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 13:11:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 13:12:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Generic helper to upload and attach images to any Eloquent model
 */

namespace App\Actions\Traits;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\WebUser;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Web\WebBlock;
use App\Models\Web\Website;

trait WithUploadModelImages
{
    use WithAttachMediaToModel;


    public function uploadImages(Group|Organisation|Shop|User|Webuser|Website|WebBlock|Product|MasterProductCategory|TradeUnit|MasterAsset|ProductCategory $model, string $scope, array $modelData): array
    {
        $medias = [];

        foreach ($modelData['images'] as $imageFile) {
            $imageData = [
                'path'         => $imageFile->getPathName(),
                'originalName' => $imageFile->getClientOriginalName(),
                'extension'    => $imageFile->guessClientExtension(),
                'checksum'     => md5_file($imageFile->getPathName()),
            ];

            $media = StoreMediaFromFile::run($model, $imageData, $scope);
            $this->attachMediaToModel($model, $media, $scope);
            $medias[] = $media;
        }

        return $medias;
    }

    /**
     * Standard validation rules for image uploads.
     */
    public function imageUploadRules(): array
    {
        return [
            'images'   => ['required', 'array'],
            'images.*' => ["mimes:jpg,png,jpeg,gif", "max:50000"],
        ];
    }
}
