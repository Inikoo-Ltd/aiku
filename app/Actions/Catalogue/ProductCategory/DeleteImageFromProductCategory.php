<?php

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromProductCategory extends OrgAction
{
    public function handle(ProductCategory $productCategory, Media $media): ProductCategory
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

        return $productCategory;
    }

    public function asController(ProductCategory $productCategory, Media $media, ActionRequest $request): void
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        $this->handle($productCategory, $media);
    }
}
