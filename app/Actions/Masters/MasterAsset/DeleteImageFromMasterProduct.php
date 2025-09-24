<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 20:31:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\DeleteImagesFromProduct;
use App\Actions\Goods\TradeUnit\DeleteImageFromTradeUnit;
use App\Actions\GrpAction;
use App\Actions\Traits\WithImageColumns;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromMasterProduct extends GrpAction
{
    use WithImageColumns;

    public function handle(MasterAsset $masterAsset, Media $media, bool $updateDependants = false): MasterAsset
    {
        $masterAsset->images()->detach($media->id);

        $updateData = [];

        foreach ($this->imageColumns() as $column) {
            if ($masterAsset->{$column} == $media->id) {
                $updateData[$column] = null;
            }
        }

        if (!empty($updateData)) {
            $masterAsset->update($updateData);
        }

        if ($updateDependants && $masterAsset->is_single_trade_unit) {
            $this->updateDependants($masterAsset, $media);
        }

        return $masterAsset;
    }

    public function updateDependants(MasterAsset $seedMasterAsset, Media $media): void
    {
        $tradeUnit = $seedMasterAsset->tradeUnits->first();
        DeleteImageFromTradeUnit::run($tradeUnit, $media);

        foreach (
            DB::table('model_has_trade_units')
                ->select('model_type', 'model_id')
                ->where('trade_unit_id', $tradeUnit->id)
                ->whereIn('model_type', ['MasterAsset', 'Product'])
                ->get() as $modelsData
        ) {
            if ($modelsData->model_type == 'MasterAsset' && $modelsData->model_id != $seedMasterAsset->id) {
                $masterAsset = MasterAsset::find($modelsData->model_id);
                if ($masterAsset && $masterAsset->is_single_trade_unit) {
                    DeleteImageFromMasterProduct::run($masterAsset, $media);
                }
            } elseif ($modelsData->model_type == 'Product') {
                $product = Product::find($modelsData->model_id);
                if ($product && $product->is_single_trade_unit) {
                    DeleteImagesFromProduct::run($product, $media);
                }
            }
        }
    }


    public function asController(MasterAsset $masterAsset, Media $media, ActionRequest $request): void
    {
        $this->initialisation($masterAsset->group, $request);

        $this->handle($masterAsset, $media, true);
    }
}
