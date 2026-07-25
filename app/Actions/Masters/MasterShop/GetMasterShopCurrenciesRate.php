<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterShop;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterShopCurrenciesRate
{
    use AsObject;

    public function handle(MasterShop $masterShop): Collection
    {
        $shopCurrencies = Shop::where('master_shop_id', $masterShop->id)
            ->select('currency_id')
            ->distinct()
            ->get();


        $baseEuro   = Currency::where('code', 'EUR')->first();// todo, this is wrong, this should come from a UI selected `seeder shop` stored in `master_shop`, if null you cuould use the first currency in the shop
        $currencies = Currency::whereIn('id', $shopCurrencies)->get();

        return $currencies->mapWithKeys(function (Currency $currency) use ($baseEuro) {
            return [
                $currency->code => [
                    'ratio_eur'       => GetCurrencyExchange::run($baseEuro, $currency),
                    'currency'        => $currency->code,
                    'currency_symbol' => $currency->symbol,
                    'currency_id'     => $currency->id,
                ]
            ];
        });
    }
}
