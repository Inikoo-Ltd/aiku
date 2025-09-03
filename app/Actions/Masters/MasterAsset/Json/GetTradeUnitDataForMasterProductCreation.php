<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\GrpAction;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\ActionRequest;

class GetTradeUnitDataForMasterProductCreation extends GrpAction
{
    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request)
    {
        $this->initialisation(group(), $request);

        return $this->handle(masterProductCategory: $masterProductCategory, tradeUnits: $this->validatedData);
    }

    public function handle(MasterProductCategory $masterProductCategory, array $tradeUnits,  $prefix = null)
    {
        $tradeUnitIds = collect($tradeUnits)->pluck('id')->filter()->toArray();
        
        if (empty($tradeUnitIds)) {
            return collect();
        }

        $foundTradeUnits = TradeUnit::whereIn('id', $tradeUnitIds)->get();
    
        $masterShop = $masterProductCategory->masterShop;

        $openShops = $masterShop->shops()->where('state', ShopStateEnum::OPEN)->get();

        $finalData = [];
        
        foreach ($openShops as $shop) {
            $finalData[] = [
                'id' => $shop->id,
                'slug' => $shop->slug,
                'code' => $shop->code,
                'name' => $shop->name,
                'currency_code' => $shop->currency->code,
                'product' => [
                    'stock' => 0,
                    'cost_price' => 0,
                    'margin' => 0,
                    'create_webpage' => false,
                    'price' => 0
                ]
            ];
        }
        
        return $finalData;
    }
}
