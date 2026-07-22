<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Sept 2025 11:43:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\GrpAction;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Traits\HasBucketImages;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetTradeUnitDataForMasterProductCreation extends GrpAction
{
    use HasBucketImages;

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
                'images'   => $this->getImagesData($tradeUnit)
            ];
        }

        $totalUnit = 1;
        if (count($tradeUnits) == 1) {
            $totalUnit = array_first($tradeUnits)['quantity'];
        }

        $masterShop = $masterProductCategory->masterShop;
        
        $openShopsQuery = $masterShop->shops()->where('state', ShopStateENUM::OPEN);

        $baseCurrency      = Currency::where('code', 'EUR')->first();

        $openOrganisations = Organisation::whereIn('id', $openShopsQuery->pluck('organisation_id'))->get();
        
        $organisationData  = [];
        $grpCosts          = [];
        $avgCost           = 0;
        $totalAvailGrpCost = 0;

        foreach ($openOrganisations as $organisation) {
            $organisationData[$organisation->id] = $this->getOrgStockData($organisation, $tradeUnits);

            $grpCost  = data_get($organisationData, "{$organisation->id}.grp_cost");
            $baseCost = $grpCost > 0
                ? formatPrice($grpCost, GetCurrencyExchange::run(group()->currency, $baseCurrency))
                : null;

            $organisationData[$organisation->id]['base_cost'] = $baseCost;

            if ($baseCost !== null) {
                $grpCosts[$organisation->code] = $baseCost;
            }
        }

        if (count($grpCosts)) {
            $avgCost = array_reduce($grpCosts, fn ($carry, $item) => $carry += $item) / count($grpCosts);
        }

        $currencies = Currency::whereIn('id', $openShopsQuery->pluck('currency_id'))->get()->keyBy('id');
        $currenciesRate   = $currencies->mapWithKeys(function ($currency) use ($baseCurrency) {
            $ratioEuro  = GetCurrencyExchange::run($baseCurrency, $currency);

            return [
                $currency->code => [
                    'ratio_eur'         => $ratioEuro,
                    'currency'          => $currency->code,
                    'currency_symbol'   => $currency->symbol,
                    'currency_id'       => $currency->id,
                ]
            ];
        });

        $masterPrices = $currenciesRate->map(fn ($ratio) => [
            'value'         => formatPrice(data_get($ratio, 'ratio_eur', 1), $avgCost),
            'independent'   => false
        ]);
        $masterRrps   = $currenciesRate->map(fn ($ratio) => [
            'value'         => formatPrice(data_get($ratio, 'ratio_eur', 1), round(($avgCost / $totalUnit) * 2.4, 2)),
            'independent'   => false
        ]);

        $finalData = [];

        foreach($openShopsQuery->get() as $shop) {
            $orgStocksData = $organisationData[$shop->organisation_id];
            $shopCurrencyCode = $shop->currency->code;

            if (!$avgCost) {
                $shopCost       = null;
                $price          = null;
                $rrp            = null;
            } else {
                $shopCost       = $masterPrices->get($shopCurrencyCode)['value'];
                $price          = round($shopCost * $shop->cost_price_ratio, 2);
                $rrp            = round($price * 2.4, 2);
            }

            $orgStocksData['shop_currency']        = $shopCurrencyCode;
            $orgStocksData['shop_cost']            = $shopCost;
            $orgStocksData['id']                   = $shop->id;
            $orgStocksData['price']                = $price ?? 0.01;
            $orgStocksData['rrp']                  = $rrp ?? 0.01;
            $orgStocksData['gross_weight']         = $tradeUnits[0]['model']->gross_weight * $tradeUnits[0]['quantity'];
            $orgStocksData['margin']               = ($orgStocksData['price'] > 0)
                ? round((($orgStocksData['price'] - $orgStocksData['shop_cost']) / $orgStocksData['price']) * 100, 2)
                : null;

            $finalData['shops'][] = $orgStocksData;
        }

        data_set($finalData, 'currencies', $currenciesRate);
        data_set($finalData, 'total_units', $totalUnit);
        data_set($finalData, 'master_prices', $masterPrices);
        data_set($finalData, 'master_rrps', $masterRrps);
        data_set($finalData, 'org_data', $organisationData);
        data_set($finalData, 'avg_org_cost', $avgCost);

        return $finalData;
    }

    public function getOrgStockData(Organisation $organisation, array $tradeUnitsDatum): array
    {
        $stock                    = null;
        $cost                     = null;
        $orgValueInWarehouse      = null;
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


                    $orgStockUnitCost  = ($orgStock->current_supplier_sku_cost ?? 0) / ($orgStock->packed_in ?? 1);
                    $orgStockUnitValue = ($orgStock->sku_value ?? 0) / ($orgStock->packed_in ?? 1);


                    $localCost = $orgStockUnitCost * $qty;
                    if ($cost == null) {
                        $cost = $localCost;
                    } else {
                        $cost += $localCost;
                    }

                    if ($orgValueInWarehouse == null) {
                        $orgValueInWarehouse = $orgStockUnitValue;
                    } else {
                        $orgValueInWarehouse += $orgStockUnitValue;
                    }

                    $organisationHasOrgStocks = true;
                }
            }
        }

        if ($cost === null) {
            $orgCost = null;
            $grpCost = null;
        } else {
            $orgCost = round($cost, 2);
            $grpCost = round($cost * GetCurrencyExchange::run($organisation->currency, $organisation->group->currency), 2);
        }

        if ($orgValueInWarehouse === null) {
            $orgValueInWarehouse = null;
            $grpValueInWarehouse = null;
        } else {
            $orgValueInWarehouse = round($orgValueInWarehouse, 2);
            $grpValueInWarehouse = round($orgValueInWarehouse * GetCurrencyExchange::run($organisation->currency, $organisation->group->currency), 2);
        }


        return [
            'org_code'               => $organisation->code,
            'stock'                  => $stock,
            'org_currency'           => $organisation->currency->code,
            'grp_currency'           => $organisation->group->currency->code,
            'org_cost'               => $orgCost,
            'grp_cost'               => $grpCost,
            'org_value_in_warehouse' => $orgValueInWarehouse,
            'grp_cost_in_warehouse'  => $grpValueInWarehouse,
            'has_org_stocks'         => $organisationHasOrgStocks,
            'create_in_shop'         => true,
        ];
    }


}
