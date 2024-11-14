<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Mar 2024 22:55:09 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\CurrencyExchange;

use App\Models\Helpers\Currency;
use App\Models\Helpers\CurrencyExchange;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetHistoricCurrencyExchange
{
    use AsAction;

    public string $commandSignature = 'currency:historic-exchange {base_currency_code} {target_currency_code} {date}';

    public function handle(Currency $baseCurrency, Currency $targetCurrency, Carbon $date): float|null
    {
        $key = 'historic-currency-exchange:'.$baseCurrency->code.'-'.$targetCurrency->code.'-'.$date->toDateString();

        $currencyExchange = (float)Cache::get($key);
        if (!$currencyExchange) {
            $currencyExchange = $this->getHistoricExchange($baseCurrency, $targetCurrency, $date);


            if ($currencyExchange) {
                Cache::add($key, $currencyExchange, now()->addDays(7));
            }
        }


        return $currencyExchange;
    }


    private function getHistoricExchange(Currency $baseCurrency, Currency $targetCurrency, Carbon $date): float|null
    {
        if ($baseCurrency->id == $targetCurrency->id) {
            return 1;
        }


        $baseExchange   = $this->getExchangeAgainstPivot($baseCurrency, $date);
        $targetExchange = $this->getExchangeAgainstPivot($targetCurrency, $date);

        if ($baseExchange && $targetExchange) {
            return $targetExchange / $baseExchange;
        }

        return null;
    }


    private function getExchangeAgainstPivot(Currency $currency, Carbon $date)
    {
        $exchangePivotCurrency = Currency::where('code', config('app.currency_exchange.pivot'))->first();

        if ($currency->id == $exchangePivotCurrency->id) {
            $exchange = 1;
        } else {
            $currencyExchange = CurrencyExchange::where('date', $date)
                ->where('currency_id', $currency->id)->first();
            if ($currencyExchange) {
                $exchange = $currencyExchange->exchange;
            } else {
                $exchangeData = FetchCurrencyExchange::run($exchangePivotCurrency, $currency, $date);
                $exchange     = $exchangeData['exchange'] ?? null;
                if ($exchange) {
                    StoreCurrencyExchange::run($currency, [
                        'exchange' => $exchange,
                        'date'     => $date->toDateString(),
                        'source'   => $exchangeData['source'] ?? null
                    ]);
                }
            }
        }

        return $exchange;
    }


    public function asCommand(Command $command): int
    {
        /** @var Currency $baseCurrency */
        $baseCurrency = Currency::where('code', $command->argument('base_currency_code'))->firstOrFail();
        /** @var Currency $targetCurrency */
        $targetCurrency = Currency::where('code', $command->argument('target_currency_code'))->firstOrFail();
        $date           = Carbon::parse($command->argument('date'));

        $exchange = $this->handle($baseCurrency, $targetCurrency, $date);
        if (!$exchange) {
            $command->error("No exchange rate found for {$baseCurrency->code}→$targetCurrency->code @(".$date->toDateString().")");

            return 1;
        }

        $command->info("FX {$baseCurrency->code}→$targetCurrency->code @(".$date->toDateString().")  : $exchange");

        return 0;
    }
}
