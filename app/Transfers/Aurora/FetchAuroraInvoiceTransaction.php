<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Models\Accounting\Invoice;
use Illuminate\Support\Facades\DB;

class FetchAuroraInvoiceTransaction extends FetchAurora
{
    protected function parseInvoiceTransaction(Invoice $invoice, bool $isFulfilment): void
    {
        if ($this->auroraModelData->{'Product Key'}) {
            $transactionId = null;


            $transaction = $invoice->customer->transactions()->where('source_id', $this->organisation->id.':'.$this->auroraModelData->{'Order Transaction Fact Key'})->first();


            if ($transaction) {
                $this->parsedData['model'] = $transaction;
                $transactionId             = $transaction->id;
                $historicAsset             = $transaction->getHistoricAssetWithTrashed();
            } else {
                $historicAsset = $this->parseHistoricAsset(
                    $this->organisation,
                    $this->auroraModelData->{'Product Key'}
                );

                $this->parsedData['model'] = $historicAsset;
            }

            if (!$isFulfilment) {
                $order = $this->parseOrder($this->organisation->id.':'.$this->auroraModelData->{'Order Key'});

                if (!$order) {
                    print "Order not found >".$this->auroraModelData->{'Order Transaction Fact Key'}."\n";
                }
                $orderId = $order?->id;
            } else {
                $orderId = null;
            }


            $quantity = $this->auroraModelData->{'Delivery Note Quantity'};
            if ($this->auroraModelData->{'Order Transaction Product Type'} == 'Service') {
                $quantity = $this->auroraModelData->{'Order Quantity'};
            }

            $taxCategory = $this->parseTaxCategory($this->auroraModelData->{'Order Transaction Tax Category Key'});


            $date = $this->parseDatetime($this->auroraModelData->{'Invoice Date'});
            if (!$date) {
                $date = $invoice->date;
            }

            $this->parsedData['transaction'] = [
                'order_id'          => $orderId,
                'transaction_id'    => $transactionId,
                'tax_category_id'   => $taxCategory->id,
                'quantity'          => $quantity,
                'gross_amount'      => $this->auroraModelData->{'Order Transaction Gross Amount'},
                'net_amount'        => $this->auroraModelData->{'Order Transaction Amount'},
                'grp_exchange'      => $invoice->grp_exchange,
                'org_exchange'      => $invoice->org_exchange,
                'fetched_at'        => now(),
                'last_fetched_at'   => now(),
                'source_id'         => $this->organisation->id.':'.$this->auroraModelData->{'Order Transaction Fact Key'},
                'date'              => $date,
                'historic_asset_id' => $historicAsset->id,
                'asset_id'          => $historicAsset->asset_id,
            ];
        } else {
            print "Warning Asset Key missing in invoice transaction >".$this->auroraModelData->{'Order Transaction Fact Key'}."\n";
        }
    }

    public function fetchInvoiceTransaction(int $id, Invoice $invoice, bool $isFulfilment): ?array
    {
        $this->auroraModelData = $this->fetchData($id);

        if ($this->auroraModelData) {
            $this->parseInvoiceTransaction($invoice, $isFulfilment);
        }

        return $this->parsedData;
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Order Transaction Fact')
            ->where('Order Transaction Fact Key', $id)->first();
    }
}
