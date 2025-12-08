<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Models\Accounting\PaymentServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraOrgPaymentServiceProvider extends FetchAurora
{
    protected function parseModel(): void
    {
        if ($this->auroraModelData->{'Payment Service Provider Type'} == 'Account') {
            return;
        }

        $code = match (Str::lower($this->auroraModelData->{'Payment Service Provider Block'})) {
            'braintree' => 'btree',

            default => Str::lower($this->auroraModelData->{'Payment Service Provider Block'})
        };

        /** @var PaymentServiceProvider $paymentServiceProvider */
        $paymentServiceProvider = PaymentServiceProvider::where('group_id', $this->organisation->group_id)
            ->where('code', $code)->firstOrFail();

        $this->parsedData['paymentServiceProvider'] = $paymentServiceProvider;

        $this->parsedData['orgPaymentServiceProvider'] = [
            'code' => $paymentServiceProvider->code.'-'.$this->organisation->code,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Payment Service Provider Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];

        $createdDateData = DB::connection('aurora')
            ->table('Payment Account Dimension')
            ->select('Payment Account From')
            ->where('Payment Account Service Provider Key', $this->auroraModelData->{'Payment Service Provider Key'})
            ->orderBy('Payment Account From')->first();

        if ($createdDateData and $this->parseDatetime($createdDateData->{'Payment Account From'})) {
            $this->parsedData['orgPaymentServiceProvider']['created_at'] = $this->parseDatetime($createdDateData->{'Payment Account From'});
        } else {
            $createdDateData = DB::connection('aurora')->table('Payment Dimension')
                ->select('Payment Created Date as date')
                ->where('Payment Service Provider Key', $this->auroraModelData->{'Payment Service Provider Key'})
                ->orderBy('Payment Created Date')->first();

            if ($createdDateData and $this->parseDatetime($createdDateData->{'date'})) {
                $this->parsedData['orgPaymentServiceProvider']['created_at'] = $this->parseDatetime($createdDateData->{'date'});
            }
        }
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Payment Service Provider Dimension')
            ->where('Payment Service Provider Key', $id)->first();
    }
}
