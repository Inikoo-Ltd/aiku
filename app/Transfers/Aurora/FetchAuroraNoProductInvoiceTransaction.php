<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Sept 2024 15:22:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FetchAuroraNoProductInvoiceTransaction extends FetchAurora
{
    protected function parseNoProductInvoiceTransaction(Invoice $invoice): void
    {
        $shippingZone = null;
        $charge = null;
        $model = null;

        $transaction = $invoice->customer->transactions()->where('source_alt_id', $this->organisation->id.':'.$this->auroraModelData->{'Order No Product Transaction Fact Key'})->first();

        if (in_array($this->auroraModelData->{'Transaction Type'}, ['Adjust', 'Credit'])) {
            $adjust = $this->parseAdjustment($this->organisation->id.':'.$this->auroraModelData->{'Order No Product Transaction Fact Key'});
            $this->parsedData['adjustment'] = $adjust;
            $model = $adjust;

            $net = $this->auroraModelData->{'Transaction Invoice Net Amount'};
            $gross = $net;
            $this->parsedData['type'] = 'Adjustment';
        } elseif ($this->auroraModelData->{'Transaction Type'} == 'Shipping') {
            if ($this->auroraModelData->{'Transaction Type Key'}) {
                if (Carbon::parse($this->auroraModelData->{'Order Date'})->gt(Carbon::parse('2019-01-01'))) {
                    $shippingZone = $this->parseShippingZone($this->organisation->id.':'.$this->auroraModelData->{'Transaction Type Key'});
                    if ($shippingZone->shop_id != $invoice->shop_id) {
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

            $charge = $invoice->shop->charges()->where('type', ChargeTypeEnum::INSURANCE)->first();
            if ($charge) {
                $model = $charge;
            }
            $gross = $this->auroraModelData->{'Transaction Gross Amount'};
            $net = $this->auroraModelData->{'Transaction Net Amount'};
            $this->parsedData['type'] = 'Charges';
        } elseif ($this->auroraModelData->{'Transaction Type'} == 'Premium') {
            $charge = $invoice->shop->charges()->where('type', ChargeTypeEnum::PREMIUM)->first();
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
            $taxCategoryID = $invoice->tax_category_id;
        } else {
            $taxCategory = $this->parseTaxCategory($taxCategoryID);
            $taxCategoryID = $taxCategory->id;
        }
        $isFulfilment = $invoice->shop->type == ShopTypeEnum::FULFILMENT;

        $orderId = null;
        if (! $isFulfilment) {
            if ($this->auroraModelData->{'Order Key'}) {
                $order = $this->parseOrder($this->organisation->id.':'.$this->auroraModelData->{'Order Key'});
                $orderId = $order?->id;
            }
        }

        $quantity = 0;
        if ($this->auroraModelData->{'Consolidated'} == 'Yes' and $this->auroraModelData->{'State'} == 'Normal') {
            $quantity = 1;
        }

        $this->parsedData['transaction'] = [
            'transaction_id' => $transaction?->id,
            'order_id' => $orderId,
            'date' => $date,
            'created_at' => $date,
            'tax_category_id' => $taxCategoryID,
            'gross_amount' => $gross,
            'net_amount' => $net,
            'source_alt_id' => $this->organisation->id.':'.$this->auroraModelData->{'Order No Product Transaction Fact Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'quantity' => $quantity,
        ];

        if ($shippingZone and $shippingZone->shop_id == $invoice->shop_id) {
            $this->parsedData['transaction']['shipping_zone_id'] = $shippingZone->id;
        }

        if ($charge) {
            $this->parsedData['transaction']['charge_id'] = $charge->id;
        }
    }

    public function fetchNoProductInvoiceTransaction(int $id, Invoice $invoice): ?array
    {
        $this->auroraModelData = $this->fetchData($id);

        if ($this->auroraModelData) {
            $this->parseNoProductInvoiceTransaction($invoice);
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
