<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategoryImages;
use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterProductCategoryImages extends GrpAction
{
    use WithActionUpdate;

    public function handle(MasterProductCategory $masterProductCategory, array $modelData, bool $updateDependants = false)
    {
        $imageTypeMapping = [
            'image_id' => 'main',
        ];

        $imageKeys = collect($imageTypeMapping)
            ->keys()
            ->filter(fn ($key) => Arr::exists($modelData, $key))
            ->toArray();

        foreach ($imageKeys as $imageKey) {
            $mediaId = $modelData[$imageKey];

            if ($mediaId === null) {
                $masterProductCategory->images()->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                    ->updateExistingPivot(
                        $masterProductCategory->images()
                            ->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                            ->first()?->id,
                        ['sub_scope' => null]
                    );
            } else {
                $media = Media::find($mediaId);

                if ($media) {
                    $masterProductCategory->images()->updateExistingPivot(
                        $media->id,
                        ['sub_scope' => $imageTypeMapping[$imageKey]]
                    );
                }
            }
        }

        $this->update($masterProductCategory, $modelData);

        if ($updateDependants) {
            $this->updateDependants($masterProductCategory, $modelData);
        }

        return $masterProductCategory;
    }

    public function updateDependants(MasterProductCategory $seedMasterProductCategory, array $modelData): void
    {
        foreach ($seedMasterProductCategory->productCategories as $productCategory) {
            UpdateProductCategoryImages::run($productCategory, $modelData, false);
        }
    }

    public function rules(): array
    {
        return [
            'image_id' => ['sometimes', 'nullable', 'exists:media,id'],
        ];
    }


    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): void
    {
        $this->initialisation($masterProductCategory->group, $request);

        $this->handle($masterProductCategory, $this->validatedData, true);
    }
}
