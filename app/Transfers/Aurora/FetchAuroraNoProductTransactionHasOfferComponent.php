<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 18:51:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;

class FetchAuroraNoProductTransactionHasOfferComponent extends FetchAurora
{
    protected function parseNoProductTransactionHasOfferComponent(Order $order): void
    {
        $transaction = $this->parseTransactionNoProduct($this->organisation->id.':'.$this->auroraModelData->{'Order No Product Transaction Fact Key'});

        if (!$transaction) {
            return;
        }

        if ($this->auroraModelData->{'Deal Component Key'} == '0') {
            $offerAllowance = $order->shop->offerAllowances()->where('is_discretionary', true)->first();
        } else {
            $offerAllowance = $this->parseOfferAllowance($this->organisation->id.':'.$this->auroraModelData->{'Deal Component Key'});
        }

        $data = [];
        if (!$offerAllowance) {


            $data           = [
                'fetch_error'      => true,
                'fetch_error_data' => [
                    'aurora_deal_component_key' => $this->auroraModelData->{'Deal Component Key'},
                ]
            ];
            $offerAllowance = $order->shop->offerAllowances()->where('is_discretionary', true)->first();



        }

        if ($offerAllowance->shop_id != $order->shop_id) {
            print 'Offer Component '.$offerAllowance->id.' does not belong to the same shop as the order '.$order->id."\n";
            dd($this->auroraModelData);
        }


        $this->parsedData['transaction']     = $transaction;
        $this->parsedData['offer_allowance'] = $offerAllowance;

        $fractionDiscount = $this->auroraModelData->{'Fraction Discount'};
        if ($fractionDiscount > 1) {
            $fractionDiscount = 1;
        }
        if ($fractionDiscount <= 0) {
            unset($fractionDiscount);
        }

        if ($this->auroraModelData->{'Amount Discount'} < 0) {
            $this->parsedData = null;
            return;
        }


        $this->parsedData['transaction_has_offer_component'] = [
            'source_alt_id'      => $this->organisation->id.':'.$this->auroraModelData->{'Order No Product Transaction Deal Key'},
            'offer_allowance_id' => $offerAllowance->id,
            'discounted_amount'  => $this->auroraModelData->{'Amount Discount'},
            'info'               => $this->auroraModelData->{'Deal Info'},
            'is_pinned'          => $this->auroraModelData->{'Order No Product Transaction Deal Pinned'} == 'Yes',
            'precursor'          => $this->auroraModelData->{'Order No Product Transaction Deal Source'},
            'fetched_at'         => now(),
            'last_fetched_at'    => now(),
            'data'               => $data,
        ];

        if (isset($fractionDiscount)) {
            $this->parsedData['transaction_has_offer_component']['discounted_amount'] = $fractionDiscount;
        }
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Order No Product Transaction Deal Bridge')
            ->where('Order No Product Transaction Deal Key', $id)->first();
    }

    public function fetchNoProductTransactionHasOfferComponent(int $id, Order $order): ?array
    {
        $this->auroraModelData = $this->fetchData($id);

        if ($this->auroraModelData) {
            $this->parseNoProductTransactionHasOfferComponent($order);
        }

        return $this->parsedData;
    }
}
