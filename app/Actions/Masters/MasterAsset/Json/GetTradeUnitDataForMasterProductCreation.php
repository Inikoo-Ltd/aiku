<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Sept 2025 11:43:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\GrpAction;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class GetTradeUnitDataForMasterProductCreation extends GrpAction
{

    public function rules(): array
    {
        return [
            'trade_units'            => ['required', 'array'],
            'trade_units.*.id'       => ['required', 'exists:trade_units,id'],
            'trade_units.*.quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request)
    {
        $this->initialisation(group(), $request);

        return $this->handle(masterProductCategory: $masterProductCategory, modelData: $this->validatedData);
    }

    public function handle(MasterProductCategory $masterProductCategory, array $modelData)
    {
        $tradeUnits = [];
        foreach ($modelData['trade_units'] as $tradeUnitData) {
            $tradeUnit    = TradeUnit::find($tradeUnitData['id']);
            $tradeUnits[] = [
                'id'       => $tradeUnit->id,
                'model'    => $tradeUnit,
                'quantity' => $tradeUnitData['quantity'],
            ];
        }

        $masterShop = $masterProductCategory->masterShop;

        $openShops = $masterShop->shops()->where('state', ShopStateEnum::OPEN)->get();

        $organisations = [];
        /** @var Shop $shop */
        foreach ($openShops as $shop) {
            $organisations[$shop->organisation_id]['org_stocks_data'] =
                $this->getOrgStockData($shop->organisation, $tradeUnits);
        }

        dd($organisations);

        $finalData = [];

        /** @var Shop $shop */
        foreach ($openShops as $shop) {
            $finalData[] = [
                'id' => $shop->id,

            ];
        }

        return $finalData;
    }

    public function getOrgStockData(Organisation $organisation, array $tradeUnitsDatum): array
    {
        $orgStocksData = [];

        foreach ($tradeUnitsDatum as $tradeUnitData) {
            $tradeUnit = $tradeUnitData['model'];

            foreach ($tradeUnit->orgStocks as $orgStock) {
                if($orgStock->organisation_id == $organisation->id){
                    $qty             = $orgStock->pivot->quantity * $tradeUnitData['quantity'];
                    $orgStocksData[] = [
                        'org_stock_id' => $orgStock->id,
                        'qty'          => $orgStock->pivot->quantity,
                        'id'           => $orgStock->id,
                        'stock'        => $orgStock->$qty,
                        'cost'         => $orgStock->cost * $qty,
                    ];
                }


            }
        }

        dd($orgStocksData);

        return $orgStocksData;
    }


}
