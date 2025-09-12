<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 20:26:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\Catalogue\Product\DeleteImagesFromProduct;
use App\Actions\GrpAction;
use App\Actions\Masters\MasterAsset\DeleteImageFromMasterProduct;
use App\Actions\Traits\WithImageColumns;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DeleteImageFromTradeUnit extends GrpAction
{
    use WithImageColumns;

    public function handle(TradeUnit $tradeUnit, Media $media, bool $updateDependants = false): TradeUnit
    {
        $tradeUnit->images()->detach($media->id);
        $tradeUnit->refresh();

        $updateData = [];

        foreach ($this->imageColumns() as $column) {
            if ($tradeUnit->{$column} == $media->id) {
                $updateData[$column] = null;
            }
        }

        if (!empty($updateData)) {
            $tradeUnit->update($updateData);
        }

        if ($updateDependants) {
            $this->updateDependencies($tradeUnit, $media);
        }

        return $tradeUnit;
    }


    public function updateDependencies(TradeUnit $tradeUnit, Media $media): void
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


    public function asController(TradeUnit $tradeUnit, Media $media, ActionRequest $request): void
    {
        $this->initialisation($tradeUnit->group, $request);

        $this->handle($tradeUnit, $media, true);
    }
}
