<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:05:20 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\GrpAction;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;

class StoreProductFromMasterProduct extends GrpAction
{
    /**
     * @throws \Throwable
     */
    public function handle(MasterAsset $masterAsset)
    {
        $productCategories = $masterAsset->masterFamily->productCategories;

        if ($productCategories) {
            foreach ($productCategories as $productCategory) {
                $orgStocks = [];
                foreach ($masterAsset->stocks as $stock) {
                    $stockOrgStocks = $stock->orgStocks()->where('organisation_id', $productCategory->organisation_id)->get();
                    
                    if ($stockOrgStocks->isEmpty()) {

                        StoreOrgStock::make()->action(
                            $productCategory->organisation,
                            $stock,
                            []
                        );
                        $stockOrgStocks = $stock->orgStocks()->where('organisation_id', $productCategory->organisation_id)->get();

                    }
                    
                    foreach ($stockOrgStocks as $orgStock) {
                        $orgStocks[$orgStock->id] = [
                            'quantity' => $orgStock->quantity_in_locations,
                        ];
                    }
                }

                $data = [
                    'code' => $masterAsset->code,
                    'name' => $masterAsset->name,
                    'price' => $masterAsset->price,
                    'unit'    => $masterAsset->unit,
                    'is_main' => true,
                    'org_stocks'  => $orgStocks
                ];
                $product = StoreProduct::run($productCategory, $data);

                foreach($masterAsset->tradeUnits as $tradeUnit) {
                    $product->tradeUnits()->attach($tradeUnit->id, [
                        'quantity' => $tradeUnit->pivot->quantity
                    ]);
                }
                
            }
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterAsset $masterAsset, int $hydratorsDelay = 0, $strict = true, $audit = true)
    {
        if (!$audit) {
            Product::disableAuditing();
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $group = $masterAsset->group;

        $this->initialisation($group, []);

        return $this->handle($masterAsset);
    }

}
