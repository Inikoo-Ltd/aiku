<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Sept 2025 11:43:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\GrpAction;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
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

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): \Illuminate\Http\Response|array
    {
        $this->initialisation(group(), $request);

        return $this->handle(masterProductCategory: $masterProductCategory, modelData: $this->validatedData);
    }

    public function handle(MasterProductCategory $masterProductCategory, array $modelData): array
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

        $organisationsData = [];
        /** @var Shop $shop */
        foreach ($openShops as $shop) {
            $organisationsData[$shop->organisation_id]['org_stocks_data'] =
                $this->getOrgStockData($shop->organisation, $tradeUnits);
        }


        $finalData = [];

        /** @var Shop $shop */
        foreach ($openShops as $shop) {
            $orgStocksData                  = $organisationsData[$shop->organisation_id]['org_stocks_data'];
            $orgStocksData['shop_currency'] = $shop->currency->code;
            $orgStocksData['shop_cost']     = round($orgStocksData['org_cost'] * GetCurrencyExchange::run($shop->organisation->currency, $shop->currency), 2);
            $orgStocksData['price']         = round($orgStocksData['shop_cost'] * $shop->cost_price_ratio, 2);
            $orgStocksData['rrp']           = round($orgStocksData['price'] * 2.4, 2);
            $orgStocksData['gross_weight']  = $tradeUnits[0]['model']->gross_weight * $tradeUnits[0]['quantity'];
            $organisationsData['images'] = $shop->organisation->media->map(fn ($media) => [
                'id'  => $media->id,
                'url' => $media->getUrl()
            ]);
            $orgStocksData['margin'] = ($orgStocksData['price'] > 0)
                ? round((($orgStocksData['price'] - $orgStocksData['shop_cost']) / $orgStocksData['price']) * 100, 2)
                : null;
            $finalData[]             = [
                'id'              => $shop->id,
                'org_stocks_data' => $orgStocksData
            ];
        }


        return $finalData;
    }

    public function getOrgStockData(Organisation $organisation, array $tradeUnitsDatum): array
    {
        $stock                    = null;
        $cost                     = null;
        $organisationHasOrgStocks = false;
        foreach ($tradeUnitsDatum as $tradeUnitData) {
            $tradeUnit = $tradeUnitData['model'];


            foreach ($tradeUnit->orgStocks as $orgStock) {
                if ($orgStock->organisation_id == $organisation->id) {
                    $qty = $tradeUnitData['quantity'];

                    $localStock = floor($orgStock->quantity_in_locations * $orgStock->pivot->quantity / $qty);
                    if ($stock == null || $localStock < $stock) {
                        $stock = $localStock;
                    }

                    $localCost = $orgStock->unit_cost * $qty;
                    if ($cost == null) {
                        $cost = $localCost;
                    } else {
                        $cost += $localCost;
                    }

                    $organisationHasOrgStocks = true;
                }
            }
        }


        return [
            'stock'          => $stock,
            'org_currency'   => $organisation->currency->code,
            'org_cost'       => round($cost, 2),
            'grp_currency'   => $organisation->group->currency->code,
            'grp_cost'       => round($cost * GetCurrencyExchange::run($organisation->currency, $organisation->group->currency), 2),
            'has_org_stocks' => $organisationHasOrgStocks,
        ];
    }


}
