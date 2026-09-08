<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Mar 2024 16:55:08 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\CurrencyExchange\Providers;

use App\Models\Helpers\Currency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchCurrencyExchangeFrankfurter
{
    use AsAction;


    public function handle(Currency $baseCurrency, Currency $targetCurrency, ?Carbon $date = null): array
    {
        if (app()->environment('testing')) {
            return [
                'status'   => 'success',
                'exchange' => 1,
                'source'   => 'F'
            ];
        }

        $url = 'https://api.frankfurter.app/';
        if ($date) {
            $url .= $date->toDateString();
        } else {
            $url .= 'latest';
        }


        $response = Http::timeout(3)->get($url, [
            'from' => $baseCurrency->code,
            'to'   => $targetCurrency->code
        ]);
        $exchange = $response->json('rates.'.$targetCurrency->code);
        if ($exchange) {
            return [
                'status'   => 'success',
                'exchange' => $exchange,
                'source'   => 'F'
            ];
        } else {
            return [
                'status'   => 'error',
                'exchange' => null,
                'source'   => 'F'
            ];
        }

    }



}
