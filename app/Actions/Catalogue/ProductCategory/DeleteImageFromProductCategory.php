<?php

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Masters\MasterProductCategory\DeleteImageFromMasterProductCategory;
use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromProductCategory extends OrgAction
{
    public function handle(ProductCategory $productCategory, Media $media, bool $updateDependants = false): ProductCategory
    {
        $productCategory->images()->detach($media->id);

        $imageColumns = [
            'image_id',
        ];

        $updateData = [];

        foreach ($imageColumns as $column) {
            if ($productCategory->{$column} == $media->id) {
                $updateData[$column] = null;
            }
        }

        if (!empty($updateData)) {
            $productCategory->update($updateData);
        }

        if ($updateDependants && $productCategory->masterProductCategory) {
            $this->updateDependants($productCategory, $media);
        }

        return $productCategory;
    }

    public function updateDependants(ProductCategory $seedProductCategory, Media $media): void
    {
        DeleteImageFromMasterProductCategory::run($seedProductCategory->masterProductCategory, $media);

        foreach ($seedProductCategory->masterProductCategory->productCategories as $productCategory) {
            if ($productCategory->id != $seedProductCategory->id) {
                DeleteImageFromProductCategory::run($productCategory, $media);
            }
        }
    }


    public function asController(ProductCategory $productCategory, Media $media, ActionRequest $request): void
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        $this->handle($productCategory, $media, true);
    }
}
