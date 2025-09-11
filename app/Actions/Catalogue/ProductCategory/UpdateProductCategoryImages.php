<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\GrpAction;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Media;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateProductCategoryImages extends OrgAction
{
    use WithActionUpdate;

    public function handle(ProductCategory $productCategory, array $modelData)
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
                $productCategory->images()->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                    ->updateExistingPivot(
                        $productCategory->images()
                        ->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                        ->first()?->id,
                        ['sub_scope' => null]
                    );
            } else {
                $media = Media::find($mediaId);

                if ($media) {
                    $productCategory->images()->updateExistingPivot(
                        $media->id,
                        ['sub_scope' => $imageTypeMapping[$imageKey]]
                    );
                }
            }
        }

        $this->update($productCategory, $modelData);

        return $productCategory;
    }

    public function rules(): array
    {
        return [
            'image_id' => ['sometimes', 'nullable', 'exists:media,id'],
        ];
    }


    public function asController(ProductCategory $productCategory, ActionRequest $request): void
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        $this->handle($productCategory, $this->validatedData);
    }
}
