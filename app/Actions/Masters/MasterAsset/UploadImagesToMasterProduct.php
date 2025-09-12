<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 13:09:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Goods\TradeUnit\UploadImagesToTradeUnit;
use App\Actions\GrpAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Actions\Traits\WithUploadModelImages;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToMasterProduct extends GrpAction
{
    use WithUploadModelImages;
    use WithAttachMediaToModel;

    public function handle(MasterAsset $model, string $scope, array $modelData, bool $updateDependants = false): array
    {
        $medias = $this->uploadImages($model, $scope, $modelData);
        if ($updateDependants && $model->is_single_trade_unit) {
            $this->updateDependants($model, $modelData, $medias, $scope);
        }

        return $medias;
    }

    public function updateDependants(MasterAsset $seedMasterAsset, array $modelData, array $medias, string $scope): void
    {
        $tradeUnit = $seedMasterAsset->tradeUnits->first();
        UploadImagesToTradeUnit::run($tradeUnit, $scope, $modelData, false);

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
                    foreach ($medias as $media) {
                        $this->attachMediaToModel($masterAsset, $media, $scope);
                    }
                }
            } elseif ($modelsData->model_type == 'Product') {
                $product = Product::find($modelsData->model_id);
                if ($product && $product->is_single_trade_unit) {
                    foreach ($medias as $media) {
                        $this->attachMediaToModel($product, $media, $scope);
                    }
                }
            }
        }
    }

    public function rules(): array
    {
        return $this->imageUploadRules();
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): void
    {
        $this->initialisation($masterAsset->group, $request);

        $this->handle($masterAsset, 'image', $this->validatedData, true);
    }
}
