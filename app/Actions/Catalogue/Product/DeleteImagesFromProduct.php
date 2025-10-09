<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 15:50:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\UI\GetProductShowcase;
use App\Actions\Goods\TradeUnit\DeleteImageFromTradeUnit;
use App\Actions\Masters\MasterAsset\DeleteImageFromMasterProduct;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\WithImageColumns;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class DeleteImagesFromProduct extends OrgAction
{
    use WithWebAuthorisation;
    use WithImageColumns;

    public function handle(Product $product, Media $media, bool $updateDependants = false): Product
    {
        $product->images()->detach($media->id);

        $updateData = [];

        foreach ($this->imageColumns() as $column) {
            if ($product->{$column} == $media->id) {
                $updateData[$column] = null;
            }
        }

        if (!empty($updateData)) {
            $product->update($updateData);
        }

        if ($updateDependants && $product->is_single_trade_unit) {
            $this->updateDependants($product, $media);
        }

        return $product;
    }

    public function updateDependants(Product $seedProduct, Media $media): void
    {
        $tradeUnit = $seedProduct->tradeUnits->first();

        DeleteImageFromTradeUnit::run($tradeUnit, $media);
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
            } elseif ($modelsData->model_type == 'Product' && $modelsData->model_id != $seedProduct->id) {
                $product = Product::find($modelsData->model_id);
                if ($product && $product->is_single_trade_unit) {
                    DeleteImagesFromProduct::run($product, $media);
                }
            }
        }
    }

    public function jsonResponse(Product $product): array
    {
        return GetProductShowcase::run($product);
    }

    public function asController(Organisation $organisation, Product $product, Media $media, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);
        $this->handle($product, $media, true);
    }
}
