<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraPayment extends FetchAurora
{
    protected function parseModel(): void
    {
        $data = [];

        $this->parsedData['customer'] = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Payment Customer Key'});
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Payment Store Key'});

        if ($this->auroraModelData->{'Payment Currency Exchange Rate'} and
            $this->auroraModelData->{'Payment Currency Exchange Rate'} != 1) {
            $data['exchange'] = $this->auroraModelData->{'Payment Currency Exchange Rate'};
        }

        $this->parsedData['paymentAccount'] = $this->parsePaymentAccount($this->organisation->id.':'.$this->auroraModelData->{'Payment Account Key'});

        if (! $this->parsedData['paymentAccount']) {
            print_r($this->auroraModelData);
            dd('Error Payment Account not found');
        }

        $state = Str::lower($this->auroraModelData->{'Payment Transaction Status'});
        $status = PaymentStatusEnum::IN_PROCESS;
        switch ($this->auroraModelData->{'Payment Transaction Status'}) {
            case 'Pending':
                $state = PaymentStateEnum::IN_PROCESS;
                break;
            case 'Completed':
                $status = PaymentStatusEnum::SUCCESS;

                break;

            case 'Cancelled':
            case 'Error':
            case 'Declined':
                $status = PaymentStatusEnum::FAIL;
        }

        $createdAt = $this->parseDatetime($this->auroraModelData->{'Payment Created Date'});
        if (! $createdAt) {
            $createdAt = $this->parseDatetime($this->auroraModelData->{'Payment Last Updated Date'});
        }
        if (! $createdAt) {
            $createdAt = $this->parseDatetime($this->auroraModelData->{'Payment Completed Date'});
        }
        if (! $createdAt) {
            $createdAt = $this->parseDatetime($this->auroraModelData->{'Payment Cancelled Date'});
        }

        $lastUpdatedDate = $this->parseDatetime($this->auroraModelData->{'Payment Last Updated Date'});
        if (! $lastUpdatedDate) {
            $lastUpdatedDate = $createdAt;
        }

        $this->parsedData['payment'] = [

            'reference' => $this->auroraModelData->{'Payment Transaction ID'},
            'amount' => $this->auroraModelData->{'Payment Transaction Amount'},
            'org_amount' => $this->auroraModelData->{'Payment Transaction Amount'} * GetHistoricCurrencyExchange::run($shop->currency, $shop->organisation->currency, $createdAt),
            'grp_amount' => $this->auroraModelData->{'Payment Transaction Amount'} * GetHistoricCurrencyExchange::run($shop->currency, $shop->group->currency, $createdAt),
            'data' => $data,
            'currency_id' => $this->parseCurrencyID($this->auroraModelData->{'Payment Currency Code'}),
            'date' => $lastUpdatedDate,
            'created_at' => $createdAt,
            'completed_at' => $this->parseDatetime($this->auroraModelData->{'Payment Completed Date'}),
            'cancelled_at' => $this->parseDatetime($this->auroraModelData->{'Payment Cancelled Date'}),
            'state' => $state,
            'status' => $status,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Payment Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'shop_id' => $shop->id,

        ];

        if ($this->parsedData['customer']) {
            $this->parsedData['payment']['customer_id'] = $this->parsedData['customer']->id;
        }

    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Payment Dimension')
            ->where('Payment Key', $id)->first();
    }
}
