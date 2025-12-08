<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Aug 2024 11:55:49 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraCredit extends FetchAurora
{
    use WithAuroraImages;

    protected function parseModel(): void
    {
        $customer = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Credit Transaction Customer Key'});

        $payment = null;
        if ($this->auroraModelData->{'Credit Transaction Payment Key'}) {
            $payment = $this->parsePayment($this->organisation->id.':'.$this->auroraModelData->{'Credit Transaction Payment Key'});
        }

        if ($payment and $payment->customer_id != $customer->id) {
            $payment = null;
            echo "\nError Payment Customer does not match Customer   ".$this->auroraModelData->{'Credit Transaction Date'}.'   >>'.$this->auroraModelData->{'Credit Transaction Key'}."<<  \n";
        }

        $date = $this->parseDatetime($this->auroraModelData->{'Credit Transaction Date'});

        $type = match ($this->auroraModelData->{'Credit Transaction Type'}) {
            'TopUp' => CreditTransactionTypeEnum::TOP_UP,
            'Payment' => CreditTransactionTypeEnum::PAYMENT,
            'Adjust' => CreditTransactionTypeEnum::ADJUST,
            'Cancel' => CreditTransactionTypeEnum::CANCEL,
            'Return' => CreditTransactionTypeEnum::RETURN,
            'PayReturn' => CreditTransactionTypeEnum::PAY_RETURN,
            'AddFundsOther' => CreditTransactionTypeEnum::ADD_FUNDS_OTHER,
            'Compensation' => CreditTransactionTypeEnum::COMPENSATION,
            'TransferIn' => CreditTransactionTypeEnum::TRANSFER_IN,
            'MoneyBack' => CreditTransactionTypeEnum::MONEY_BACK,
            'TransferOut' => CreditTransactionTypeEnum::TRANSFER_OUT,
            'RemoveFundsOther' => CreditTransactionTypeEnum::REMOVE_FUNDS_OTHER,
            default => null
        };

        $this->parsedData['customer'] = $customer;

        $this->parsedData['credit'] =
            [
                'date' => $date,
                'type' => $type,
                'amount' => $this->auroraModelData->{'Credit Transaction Amount'},
                'org_exchange' => GetHistoricCurrencyExchange::run($this->parsedData['customer']->shop->currency, $this->parsedData['customer']->organisation->currency, $date),
                'grp_exchange' => GetHistoricCurrencyExchange::run($this->parsedData['customer']->shop->currency, $this->parsedData['customer']->group->currency, $date),
                'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Credit Transaction Key'},
                'fetched_at' => now(),
                'last_fetched_at' => now(),
            ];

        if ($payment) {
            $this->parsedData['credit']['payment_id'] = $payment->id;
        }

    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Credit Transaction Fact')
            ->where('Credit Transaction Key', $id)->first();
    }
}
