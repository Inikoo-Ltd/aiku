<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 13:09:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\GrpAction;
use App\Actions\Traits\WithUploadModelImages;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToTradeUnit extends GrpAction
{
    use WithUploadModelImages;

    public function handle(TradeUnit $model, string $scope, array $modelData): array
    {
        $medias = $this->uploadImages($model, $scope, $modelData);

        return $medias;
    }

    public function updateDependencies(TradeUnit $tradeUnit, array $modelData): void
    {
        foreach (DB::table('model_has_trade_units')
            ->select('model_type', 'model_id')
            ->where('trade_unit_id', $tradeUnit->id)
            ->whereIn('model_type', ['MasterAsset','Product'])
            ->get() as $modelsData) {
            if ($modelsData->model_type == 'MasterAsset') {
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
        return $this->imageUploadRules();
    }

    public function asController(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        $this->initialisation($tradeUnit->group, $request);

        $this->handle($tradeUnit, 'image', $this->validatedData, true);
    }
}
