<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Mar 2024 18:14:10 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\CurrencyExchange\Providers;

use App\Models\Helpers\Currency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchCurrencyExchangeCurrencyBeacon
{
    use AsAction;


    public function handle(Currency $baseCurrency, Currency $targetCurrency, ?Carbon $date = null): array
    {

        if (app()->environment('testing')) {
            return [
                'status'   => 'success',
                'exchange' => 1,
                'source'   => 'CB'
            ];
        }

        $apiKeys = config('app.currency_exchange.providers.currency_beacon');
        if (!$apiKeys) {
            return [
                'status'   => 'error',
                'exchange' => null,
                'source'   => 'CB'
            ];
        }
        $apiKeys = explode(',', $apiKeys);

        $apiKey     = $apiKeys[array_rand($apiKeys)];
        $url        = 'https://api.currencybeacon.com/v1/';
        $parameters = [
            'base'    => $baseCurrency->code,
            'symbols' => $targetCurrency->code,
            'api_key' => $apiKey
        ];


        if ($date) {
            $url .= 'historical';
            data_set($parameters, 'date', $date->toDateString());
        } else {
            $url .= 'latest';
        }


        $response = Http::timeout(3)->get($url, $parameters);
        if ($response->status() != 200) {
            return [
                'status'   => 'error',
                'exchange' => null,
                'source'   => 'CB'
            ];
        }

        $exchange = $response->json('rates.'.$targetCurrency->code);
        if ($exchange) {
            return [
                'status'   => 'success',
                'exchange' => $exchange,
                'source'   => 'CB'
            ];
        } else {
            return [
                'status'   => 'error',
                'exchange' => null,
                'source'   => 'CB'
            ];
        }
    }


}
