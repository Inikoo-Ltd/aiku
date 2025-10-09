<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\Goods\TradeUnit\UpdateTradeUnitImages;
use App\Actions\Masters\MasterAsset\UpdateMasterProductImages;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateProductImages extends OrgAction
{
    use WithActionUpdate;

    public function handle(Product $product, array $modelData, bool $updateDependants = false): Product
    {

        $imageTypeMapping = [
            'image_id' => 'main',
            'front_image_id' => 'front',
            '34_image_id' => '34',
            'left_image_id' => 'left',
            'right_image_id' => 'right',
            'back_image_id' => 'back',
            'top_image_id' => 'top',
            'bottom_image_id' => 'bottom',
            'size_comparison_image_id' => 'size_comparison',
            'lifestyle_image_id' => 'lifestyle',
            'art1_image_id' => 'art1',
            'art2_image_id' => 'art2',
            'art3_image_id' => 'art3',
            'art4_image_id' => 'art4',
            'art5_image_id' => 'art5',
        ];

        $imageKeys = collect($imageTypeMapping)
            ->keys()
            ->filter(fn ($key) => Arr::exists($modelData, $key))
            ->toArray();

        foreach ($imageKeys as $imageKey) {
            $mediaId = $modelData[$imageKey];

            if ($mediaId === null) {
                $product->images()->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                    ->updateExistingPivot(
                        $product->images()
                        ->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                        ->first()?->id,
                        ['sub_scope' => null]
                    );
            } else {
                $media = Media::find($mediaId);

                if ($media) {
                    $product->images()->updateExistingPivot(
                        $media->id,
                        ['sub_scope' => $imageTypeMapping[$imageKey]]
                    );
                }
            }
        }

        data_set($modelData, 'bucket_images', true);

        $this->update($product, $modelData);

        UpdateProductWebImages::run($product);

        if ($updateDependants && $product->is_single_trade_unit) {
            $this->updateDependants($product, $modelData);
        }

        return $product;
    }

    public function updateDependants(Product $seedProduct, array $modelData): void
    {
        $tradeUnit = $seedProduct->tradeUnits->first();
        UpdateTradeUnitImages::run($tradeUnit, $modelData, false);

        foreach (DB::table('model_has_trade_units')
            ->select('model_type', 'model_id')
            ->where('trade_unit_id', $tradeUnit->id)
            ->whereIn('model_type', ['MasterAsset','Product'])
            ->get() as $modelsData) {
            if ($modelsData->model_type == 'MasterAsset') {
                $masterAsset = MasterAsset::find($modelsData->model_id);
                if ($masterAsset && $masterAsset->is_single_trade_unit) {
                    UpdateMasterProductImages::run($masterAsset, $modelData);
                }
            } elseif ($modelsData->model_type == 'Product' && $modelsData->model_id != $seedProduct->id) {
                $product = Product::find($modelsData->model_id);
                if ($product && $product->is_single_trade_unit) {
                    UpdateProductImages::run($product, $modelData);
                }
            }
        }
    }


    public function rules(): array
    {
        return [
            'image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'front_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            '34_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'left_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'right_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'back_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'top_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'bottom_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'size_comparison_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'lifestyle_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'art1_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'art2_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'art3_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'art4_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'art5_image_id' => ['sometimes', 'nullable', 'exists:media,id'],
            'video_url' => ['sometimes', 'nullable'],
        ];
    }


    public function asController(Product $product, ActionRequest $request): void
    {
        $this->initialisationFromShop($product->shop, $request);

        $this->handle($product, $this->validatedData, true);
    }
}
