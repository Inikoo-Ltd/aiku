<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Apr 2023 17:00:29 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\CurrencyExchange;

use App\Models\Helpers\Currency;
use App\Models\Helpers\CurrencyExchange;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCurrencyExchange
{
    use AsAction;

    public string $commandSignature = 'currency:exchange {base_currency_code} {target_currency_code}';

    public function handle(Currency $baseCurrency, Currency $targetCurrency): float|null
    {
        if ($baseCurrency->code === $targetCurrency->code) {
            return 1.0;
        }

        $key  = 'current-currency-exchange:'.$baseCurrency->code.'-'.$targetCurrency->code;

        $currencyExchange = (float)Cache::get($key);
        if (!$currencyExchange) {
            try {

                $exchangeData          = FetchCurrencyExchange::run($baseCurrency, $targetCurrency);
                $currencyExchange      = $exchangeData['exchange'] ?? null;

            } catch (Exception) {
                $currencyExchange = null;
            }

            $currencyExchange ??= $this->latestStoredExchange($baseCurrency, $targetCurrency);

            if ($currencyExchange) {
                Cache::add($key, $currencyExchange, now()->addHours(6));
            }
        }


        return $currencyExchange;
    }


    private function latestStoredExchange(Currency $baseCurrency, Currency $targetCurrency): ?float
    {
        $pivotCode = config('app.currency_exchange.pivot');

        $againstPivot = fn (Currency $currency): ?float => $currency->code === $pivotCode
            ? 1.0
            : CurrencyExchange::where('currency_id', $currency->id)->latest('date')->value('exchange');

        $baseExchange   = $againstPivot($baseCurrency);
        $targetExchange = $againstPivot($targetCurrency);

        return $baseExchange && $targetExchange ? (float) $targetExchange / (float) $baseExchange : null;
    }

    public function asCommand(Command $command): int
    {
        $baseCurrency   = Currency::where('code', $command->argument('base_currency_code'))->firstOrFail();
        $targetCurrency = Currency::where('code', $command->argument('target_currency_code'))->firstOrFail();


        $exchange = $this->handle($baseCurrency, $targetCurrency);

        $command->info("Current exchange {$baseCurrency->code}→$targetCurrency->code : $exchange");

        return 0;
    }
}
