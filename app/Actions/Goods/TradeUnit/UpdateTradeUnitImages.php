<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Goods\TradeUnit;

use App\Actions\Catalogue\Product\CloneProductImagesFromTradeUnits;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterAsset\CloneMasterAssetImagesFromTradeUnits;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithImageUpdate;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateTradeUnitImages extends GrpAction
{
    use WithActionUpdate;
    use WithImageUpdate;

    public function handle(TradeUnit $tradeUnit, array $modelData, bool $updateDependants = false): TradeUnit
    {
        $this->updateModelImages($tradeUnit, $modelData);

        data_set($modelData, 'bucket_images', true);

        $this->update($tradeUnit, $modelData);

        if ($updateDependants) {
            $this->updateDependencies($tradeUnit);
        }

        return $tradeUnit;
    }

    public function updateDependencies(TradeUnit $tradeUnit): void
    {
        foreach (
            DB::table('model_has_trade_units')
                ->select('model_type', 'model_id')
                ->where('trade_unit_id', $tradeUnit->id)
                ->whereIn('model_type', ['MasterAsset', 'Product'])
                ->get() as $modelsData
        ) {
            if ($modelsData->model_type == 'MasterAsset') {
                $masterAsset = MasterAsset::find($modelsData->model_id);
                if ($masterAsset && $masterAsset->is_single_trade_unit) {
                    CloneMasterAssetImagesFromTradeUnits::run($masterAsset);
                }
            } elseif ($modelsData->model_type == 'Product') {
                $product = Product::find($modelsData->model_id);
                if ($product && $product->is_single_trade_unit) {
                    CloneProductImagesFromTradeUnits::run($product);
                }
            }
        }
    }

    public function rules(): array
    {
        return $this->imageUpdateRules();
    }


    public function asController(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        $this->initialisation($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData, true);
    }
}
