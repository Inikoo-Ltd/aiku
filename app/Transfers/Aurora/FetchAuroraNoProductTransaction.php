<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Sept 2024 23:18:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Ordering\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FetchAuroraNoProductTransaction extends FetchAurora
{
    public function parseNoProductTransaction(Order $order): void
    {
        $shippingZone = null;
        $model = null;

        if (in_array($this->auroraModelData->{'Transaction Type'}, ['Adjust', 'Credit'])) {
            $adjust = $this->parseAdjustment($this->organisation->id.':'.$this->auroraModelData->{'Order No Product Transaction Fact Key'});

            $model = $adjust;
            $this->parsedData['adjustment'] = $adjust;

            $net = $this->auroraModelData->{'Transaction Invoice Net Amount'};
            $gross = $net;
            $this->parsedData['type'] = 'Adjustment';
        } elseif ($this->auroraModelData->{'Transaction Type'} == 'Shipping') {
            if ($this->auroraModelData->{'Transaction Type Key'}) {
                if (Carbon::parse($this->auroraModelData->{'Order Date'})->gt(Carbon::parse('2019-01-01'))) {
                    $shippingZone = $this->parseShippingZone($this->organisation->id.':'.$this->auroraModelData->{'Transaction Type Key'});
                    if ($shippingZone->shop_id != $order->shop_id) {
                        $shippingZone = null;
                    }
                }

                $model = $shippingZone;
            }

            $gross = $this->auroraModelData->{'Transaction Gross Amount'};
            $net = $this->auroraModelData->{'Transaction Net Amount'};
            $this->parsedData['type'] = $this->auroraModelData->{'Transaction Type'};
        } elseif ($this->auroraModelData->{'Transaction Type'} == 'Charges') {
            if ($this->auroraModelData->{'Transaction Type Key'}) {
                $charge = $this->parseCharge($this->organisation->id.':'.$this->auroraModelData->{'Transaction Type Key'});
                $model = $charge;
            }

            $gross = $this->auroraModelData->{'Transaction Gross Amount'};
            $net = $this->auroraModelData->{'Transaction Net Amount'};
            $this->parsedData['type'] = $this->auroraModelData->{'Transaction Type'};
        } elseif ($this->auroraModelData->{'Transaction Type'} == 'Insurance') {

            $charge = $order->shop->charges()->where('type', ChargeTypeEnum::INSURANCE)->first();
            if ($charge) {
                $model = $charge;
            }

            $gross = $this->auroraModelData->{'Transaction Gross Amount'};
            $net = $this->auroraModelData->{'Transaction Net Amount'};
            $this->parsedData['type'] = 'Charges';
        } elseif ($this->auroraModelData->{'Transaction Type'} == 'Premium') {

            $charge = $order->shop->charges()->where('type', ChargeTypeEnum::PREMIUM)->first();
            if ($charge) {
                $model = $charge;
            }

            $gross = $this->auroraModelData->{'Transaction Gross Amount'};
            $net = $this->auroraModelData->{'Transaction Net Amount'};
            $this->parsedData['type'] = 'Charges';
        } elseif ($this->auroraModelData->{'Transaction Type'} == 'Refund') {
            return;
        } else {
            dd($this->auroraModelData);
        }

        $this->parsedData['model'] = $model;

        $date = $this->parseDatetime($this->auroraModelData->{'Order Date'});
        $date = new Carbon($date);

        $taxCategoryID = $this->auroraModelData->{'Order No Product Transaction Tax Category Key'};

        if (! $taxCategoryID) {
            $taxCategoryID = $order->tax_category_id;
        } else {
            $taxCategory = $this->parseTaxCategory($taxCategoryID);
            $taxCategoryID = $taxCategory->id;
        }

        $quantityDispatched = 0;
        if ($this->auroraModelData->{'Consolidated'} == 'Yes' and $this->auroraModelData->{'State'} == 'Normal') {
            $quantityDispatched = 1;
        }

        $this->parsedData['transaction'] = [
            'date' => $date,
            'created_at' => $date,
            'tax_category_id' => $taxCategoryID,
            'quantity_ordered' => 1,
            'quantity_dispatched' => $quantityDispatched,
            'gross_amount' => $gross,
            'net_amount' => $net,
            'source_alt_id' => $this->organisation->id.':'.$this->auroraModelData->{'Order No Product Transaction Fact Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    public function fetchNoProductTransaction(int $id, Order $order): ?array
    {
        $this->auroraModelData = $this->fetchData($id);

        if ($this->auroraModelData) {
            $this->parseNoProductTransaction($order);
        }

        return $this->parsedData;
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Order No Product Transaction Fact')
            ->where('Order No Product Transaction Fact Key', $id)->first();
    }
}
