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
use App\Actions\Traits\WithImageUpdate;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterProductImages extends GrpAction
{
    use WithActionUpdate;
    use WithImageUpdate;

    public function handle(MasterAsset $masterAsset, array $modelData, bool $updateDependants = false): MasterAsset
    {
        $this->updateModelImages($masterAsset, $modelData);

        data_set($modelData, 'bucket_images', true);

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
                if ($masterAsset && $masterAsset->is_single_trade_unit) {
                    UpdateMasterProductImages::run($masterAsset, $modelData);
                }
            } elseif ($modelsData->model_type == 'Product') {
                $product = Product::find($modelsData->model_id);
                if ($product && $product->is_single_trade_unit) {
                    UpdateProductImages::run($product, $modelData);
                }
            }
        }
    }


    public function rules(): array
    {
        return $this->imageUpdateRules();
    }


    public function asController(MasterAsset $masterAsset, ActionRequest $request): void
    {
        $this->initialisation($masterAsset->group, $request);

        $this->handle($masterAsset, $this->validatedData, true);
    }
}
