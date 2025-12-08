<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraPaymentAccount extends FetchAurora
{
    protected function parseModel(): void
    {
        if ($this->auroraModelData->{'Payment Account Block'} == 'Accounts' or $this->auroraModelData->{'Payment Account Block'} == 'Other') {
            return;
        }

        $type = match ($this->auroraModelData->{'Payment Account Block'}) {
            'Accounts' => PaymentAccountTypeEnum::ACCOUNT,
            'Paypal' => PaymentAccountTypeEnum::PAYPAL,
            'WP' => PaymentAccountTypeEnum::WORLD_PAY,
            'Sofort' => PaymentAccountTypeEnum::SOFORT,
            'BTree','BTreePaypal' => PaymentAccountTypeEnum::BRAINTREE,
            'Bank' => PaymentAccountTypeEnum::BANK,
            'Cash' => PaymentAccountTypeEnum::CASH,
            'Hokodo' => PaymentAccountTypeEnum::HOKODO,
            'Checkout' => PaymentAccountTypeEnum::CHECKOUT,
            'Pastpay' => PaymentAccountTypeEnum::PASTPAY,
            'ConD' => PaymentAccountTypeEnum::CASH_ON_DELIVERY,
            default => 'unknown'
        };

        if ($type === 'unknown') {
            dd($this->auroraModelData->{'Payment Account Block'});
        }

        $this->parsedData['orgPaymentServiceProvider'] = $this->parseOrgPaymentServiceProvider($this->organisation->id.':'.$this->auroraModelData->{'Payment Account Service Provider Key'});

        $code = $this->auroraModelData->{'Payment Account Code'};
        $code = str_replace('.', '-', $code);
        $code = str_replace('_', '-', $code);

        $code = strtolower($code);

        if ($this->organisation->slug == 'aw') {
            if ($code == 'bankeur') {
                $code = 'aw-bank-eur';
            }
            if ($code == 'bankgbp') {
                $code = 'aw-bank-gbp';
            }

            if ($code == 'bankpln') {
                $code = 'aw-bank-pln';
            }
        }
        if ($this->organisation->slug == 'sk') {
            if ($code == 'bankpln') {
                $code = 'sk-bank-pln';
            }
            if ($code == 'bankpln2') {
                $code = 'sk-bank-pln2';
            }
            if ($code == 'bankeur') {
                $code = 'sk-bank-eur';
            }

            if (in_array($code, ['btree', 'btree-pln', 'paypal', 'checkout', 'hokodo'])) {
                $code = 'sk-'.$code;
            }
        }

        if ($this->organisation->slug == 'es') {
            if (in_array($code, ['btree', 'paypal', 'checkout', 'hokodo'])) {
                $code = 'es-'.$code;
            }
        }

        if ($this->organisation->slug == 'aroma') {
            if ($code == 'bankgbp') {
                $code = 'aro-bank-gbp';
            }
            if (in_array($code, ['btree', 'paypal', 'checkout', 'hokodo', 'cash', 'paypal-btree'])) {
                $code = 'aro-'.$code;
            }
        }
        $data = [];

        if ($type == PaymentAccountTypeEnum::BANK) {
            $data = [
                'bank' => [
                    'recipient' => $this->auroraModelData->{'Payment Account Recipient Holder'},
                    'name' => $this->auroraModelData->{'Payment Account Recipient Bank Name'},
                    'account' => $this->auroraModelData->{'Payment Account Recipient Bank Account Number'},
                    'sort_code' => $this->auroraModelData->{'Payment Account Recipient Bank Code'},
                    'iban' => $this->auroraModelData->{'Payment Account Recipient Bank IBAN'},
                    'swift' => $this->auroraModelData->{'Payment Account Recipient Bank Swift'},
                    'bic' => '',
                    'address' => $this->auroraModelData->{'Payment Account Recipient Address'},
                ],
            ];
        } elseif ($type == PaymentAccountTypeEnum::CHECKOUT) {
            $data = [
                'credentials' => [
                    'public_key' => $this->auroraModelData->{'Payment Account Login'},
                    'secret_key' => $this->auroraModelData->{'Payment Account Password'},

                ],
            ];
        } elseif ($type == PaymentAccountTypeEnum::PASTPAY) {
            $data = [
                'credentials' => [
                    'api_key' => $this->auroraModelData->{'Payment Account Password'},
                ],
            ];
        } elseif ($type == PaymentAccountTypeEnum::CASH_ON_DELIVERY) {

            $countryCodes = explode(',', $this->auroraModelData->{'Payment Account Settings'});
            $countries = [];
            foreach ($countryCodes as $countryCode) {
                $countries[] = $this->parseCountryID($countryCode);
            }
            $data = [
                'countries' => $countries,
            ];
        } elseif ($type == PaymentAccountTypeEnum::BRAINTREE) {
            $data = [
                'credentials' => [
                    'client_id' => $this->auroraModelData->{'Payment Account Login'},
                    'client_secret' => $this->auroraModelData->{'Payment Account Password'},

                ],
                'payment_methods' => $this->auroraModelData->{'Payment Account Block'} == 'BTreePaypal' ? ['paypal'] : ['card', 'paypal'],
            ];
        }

        $this->parsedData['paymentAccount'] = [
            'code' => $code,
            'name' => $this->auroraModelData->{'Payment Account Name'},
            'type' => $type,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Payment Account Key'},
            'data' => $data,
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];

        if ($this->parseDatetime($this->auroraModelData->{'Payment Account From'})) {
            $this->parsedData['paymentAccount']['created_at'] = $this->parseDatetime($this->auroraModelData->{'Payment Account From'});
        } else {
            $createdDateData = DB::connection('aurora')->table('Payment Dimension')
                ->select('Payment Created Date as date')
                ->where('Payment Account Key', $this->auroraModelData->{'Payment Account Key'})
                ->orderBy('Payment Created Date')->first();

            if ($createdDateData and $this->parseDatetime($createdDateData->{'date'})) {
                $this->parsedData['orgPaymentServiceProvider']['created_at'] = $this->parseDatetime($createdDateData->{'date'});
            }
        }
    }

    protected function fetchData(
        $id
    ): ?object {
        return DB::connection('aurora')
            ->table('Payment Account Dimension')
            ->where('Payment Account Key', $id)->first();
    }
}
