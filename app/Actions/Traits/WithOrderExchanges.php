<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Jul 2024 18:21:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use Illuminate\Support\Arr;

trait WithOrderExchanges
{
    protected function processExchanges($modelData, $shop, $field = 'net_amount'): array
    {
        if (!Arr::exists($modelData, 'org_exchange')) {
            $orgExchange = GetCurrencyExchange::run($shop->currency, $shop->organisation->currency);
        } else {
            $orgExchange = Arr::get($modelData, 'org_exchange');
        }

        if (!Arr::exists($modelData, 'org_exchange')) {
            $grpExchange = GetCurrencyExchange::run($shop->currency, $shop->organisation->group->currency);
        } else {
            $grpExchange = Arr::get($modelData, 'grp_exchange');
        }

        data_set($modelData, 'org_exchange', $orgExchange, overwrite: false);
        data_set($modelData, 'grp_exchange', $grpExchange, overwrite: false);

        $amount = Arr::get($modelData, $field);
        if (!is_numeric($amount)) {
            $amount = 0;
        }

        if ($orgExchange) {
            $orgAmount = $amount * $orgExchange;
        } else {
            $orgAmount = null;
        }

        if ($grpExchange) {
            $grpAmount = $amount * $grpExchange;
        } else {
            $grpAmount = null;
        }

        data_set($modelData, 'org_'.$field, $orgAmount);
        data_set($modelData, 'grp_'.$field, $grpAmount);

        return $modelData;
    }
}
