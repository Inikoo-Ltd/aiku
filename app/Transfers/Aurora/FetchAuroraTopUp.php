<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Nov 2024 22:23:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use App\Models\Helpers\Currency;
use Illuminate\Support\Facades\DB;

class FetchAuroraTopUp extends FetchAurora
{
    protected function parseModel(): void
    {
        if (! $this->auroraModelData->{'Top Up Payment Key'}) {
            return;
        }

        $payment = $this->parsePayment($this->organisation->id.':'.$this->auroraModelData->{'Top Up Payment Key'});

        if (! $payment) {
            return;
        }

        $customer = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Top Up Customer Key'});

        $currencyId = $this->parseCurrencyID($this->auroraModelData->{'Top Up Currency Code'});
        /** @var Currency $currency */
        $currency = Currency::findOrFail($currencyId);

        $date = $this->parseDatetime($this->auroraModelData->{'Top Up Date'});

        $amount = $this->auroraModelData->{'Top Up Amount'};

        $orgExchange = GetHistoricCurrencyExchange::run($currency, $customer->organisation->currency, $date);
        $grpExchange = GetHistoricCurrencyExchange::run($currency, $customer->group->currency, $date);

        $status = match ($this->auroraModelData->{'Top Up Status'}) {
            'InProcess' => TopUpStatusEnum::IN_PROCESS,
            'Error' => TopUpStatusEnum::FAIL,
            default => TopUpStatusEnum::SUCCESS
        };

        if ($payment->customer_id != $customer->id) {
            dd('error top up (customer)');
            // $payment->customer_id = $customer->id;
            // $payment->save();
        }

        if ($payment->currency_id != $currency->id) {
            dd('error top up (currency)');
        }

        $this->parsedData['payment'] = $payment;
        $this->parsedData['topUp'] = [
            'reference' => 'au-'.$this->organisation->slug.'-'.$this->auroraModelData->{'Top Up Key'},
            'created_at' => $date,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Top Up Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'status' => $status,
            'amount' => $amount,
            'org_amount' => round($amount * $orgExchange, 2),
            'grp_amount' => round($amount * $grpExchange, 2),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Top Up Dimension')
            ->where('Top Up Key', $id)->first();
    }
}
