<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 21:37:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\DeleteImageFromProductCategory;
use App\Actions\GrpAction;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromMasterProductCategory extends GrpAction
{
    public function handle(MasterProductCategory $masterProductCategory, Media $media, bool $updateDependants = false): MasterProductCategory
    {
        $masterProductCategory->images()->detach($media->id);

        $imageColumns = [
            'image_id',
        ];

        $updateData = [];

        foreach ($imageColumns as $column) {
            if ($masterProductCategory->{$column} == $media->id) {
                $updateData[$column] = null;
            }
        }

        if (!empty($updateData)) {
            $masterProductCategory->update($updateData);
        }

        if ($updateDependants) {
            $this->updateDependants($masterProductCategory, $media);
        }

        return $masterProductCategory;
    }

    public function updateDependants(MasterProductCategory $seedMasterProductCategory, Media $media): void
    {
        foreach ($seedMasterProductCategory->productCategories as $productCategory) {
            DeleteImageFromProductCategory::run($productCategory, $media);
        }
    }

    public function asController(MasterProductCategory $masterProductCategory, Media $media, ActionRequest $request): void
    {
        $this->initialisation($masterProductCategory->group, $request);

        $this->handle($masterProductCategory, $media, true);
    }
}
