<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 15:50:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Actions\Traits\WithUploadModelImages;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToProduct extends OrgAction
{
    use WithUploadModelImages;
    use WithAttachMediaToModel;

    public function handle(Product $model, string $scope, array $modelData, bool $updateDependants = false): array
    {
        $medias = $this->uploadImages($model, $scope, $modelData);
        if ($updateDependants && $model->is_single_trade_unit) {
            $this->updateDependants($model, $medias, $scope);
        }

        return $medias;
    }

    public function updateDependants(Product $seedProduct, array $medias, string $scope): void
    {
        $tradeUnit = $seedProduct->tradeUnits->first();
        foreach ($medias as $media) {
            $this->attachMediaToModel($tradeUnit, $media, $scope);
        }


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
                    foreach ($medias as $media) {
                        $this->attachMediaToModel($masterAsset, $media, $scope);
                    }
                }
            } elseif ($modelsData->model_type == 'Product' && $modelsData->model_id != $seedProduct->id) {
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

    public function asController(Product $product, ActionRequest $request): void
    {
        $this->initialisationFromShop($product->shop, $request);

        $this->handle($product, 'image', $this->validatedData, true);
    }
}
