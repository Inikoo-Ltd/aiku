<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\UpdateProductImages;
use App\Actions\Goods\TradeUnit\UpdateTradeUnitImages;
use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterProductImages extends GrpAction
{
    use WithActionUpdate;

    public function handle(MasterAsset $masterAsset, array $modelData, bool $updateDependants = false): MasterAsset
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
        ];

        $imageKeys = collect($imageTypeMapping)
            ->keys()
            ->filter(fn ($key) => Arr::exists($modelData, $key))
            ->toArray();

        foreach ($imageKeys as $imageKey) {
            $mediaId = $modelData[$imageKey];

            if ($mediaId === null) {
                $masterAsset->images()->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                    ->updateExistingPivot(
                        $masterAsset->images()
                        ->wherePivot('sub_scope', $imageTypeMapping[$imageKey])
                        ->first()?->id,
                        ['sub_scope' => null]
                    );
            } else {
                $media = Media::find($mediaId);

                if ($media) {
                    $masterAsset->images()->updateExistingPivot(
                        $media->id,
                        ['sub_scope' => $imageTypeMapping[$imageKey]]
                    );
                }
            }
        }

        $this->update($masterAsset, $modelData);

        if ($updateDependants && $masterAsset->is_single_trade_unit) {
            $this->updateDependants($masterAsset, $modelData);
        }


        return $masterAsset;
    }

    public function updateDependants(MasterAsset $seedMasterAsset, array $modelData): void
    {
        $tradeUnit = $seedMasterAsset->tradeUnits->first();
        UpdateTradeUnitImages::run($tradeUnit, $modelData, false);

        foreach (DB::table('model_has_trade_units')
            ->select('model_type', 'model_id')
            ->where('trade_unit_id', $tradeUnit->id)
            ->whereIn('model_type', ['MasterAsset','Product'])
            ->get() as $modelsData) {
            if ($modelsData->model_type == 'MasterAsset' && $modelsData->model_id != $seedMasterAsset->id) {
                $masterAsset = MasterAsset::find($modelsData->model_id);
                if ($masterAsset) {
                    UpdateMasterProductImages::run($masterAsset, $modelData);
                }
            } elseif ($modelsData->model_type == 'Product') {
                $product = Product::find($modelsData->model_id);
                if ($product) {
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
            'video_url' => ['sometimes', 'nullable'],
        ];
    }


    public function asController(MasterAsset $masterAsset, ActionRequest $request): void
    {
        $this->initialisation($masterAsset->group, $request);

        $this->handle($masterAsset, $this->validatedData, true);
    }
}
