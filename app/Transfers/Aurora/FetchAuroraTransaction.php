<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Ordering\Transaction\TransactionFailStatusEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FetchAuroraTransaction extends FetchAurora
{
    protected function parseModel(): void
    {
        if ($this->auroraModelData->{'Order Transaction Type'} == 'Refund') {
            return;
        }

        if ($this->auroraModelData->{'Product Key'}) {
            // enum('In Process by Customer','Submitted by Customer','In Process','Ready to Pick','Picking','Ready to Pack','Ready to Ship','Dispatched','Unknown','Packing','Packed','Packed Done','Cancelled','No Picked Due Out of Stock','No Picked Due No Authorised','No Picked Due Not Found','No Picked Due Other','Suspended','Cancelled by Customer','Out of Stock in Basket')
            if (in_array($this->auroraModelData->{'Current Dispatching State'}, ['Out of Stock in Basket', 'In Process by Customer'])) {
                return;
            }

            $historicAsset = $this->parseHistoricAsset(
                $this->organisation,
                $this->auroraModelData->{'Product Key'}
            );

            // enum('In Process by Customer','Submitted by Customer','In Process','Ready to Pick','Picking','Ready to Pack','Ready to Ship','Dispatched','Unknown','Packing','Packed','Packed Done','Cancelled','No Picked Due Out of Stock','No Picked Due No Authorised','No Picked Due Not Found','No Picked Due Other','Suspended','Cancelled by Customer','Out of Stock in Basket')

            $state = match ($this->auroraModelData->{'Current Dispatching State'}) {
                'In Process by Customer' => TransactionStateEnum::CREATING,
                'In Process', 'Submitted by Customer' => TransactionStateEnum::SUBMITTED,
                'Ready to Pick', 'Picking', 'Ready to Pack', 'Packing', 'Packed', 'Packed Done' => TransactionStateEnum::HANDLING,
                'Ready to Ship' => TransactionStateEnum::FINALISED,
                'Dispatched' => TransactionStateEnum::DISPATCHED,
                'No Picked Due Out of Stock', 'No Picked Due No Authorised', 'No Picked Due Not Found', 'No Picked Due Other', 'Cancelled', 'Suspended', 'Cancelled by Customer' => TransactionStateEnum::CANCELLED,
                'Unknown' => null
            };

            $status = match ($this->auroraModelData->{'Current Dispatching State'}) {
                'In Process by Customer', 'Out of Stock in Basket', 'In Process' => TransactionStatusEnum::CREATING,
                'Picking', 'Ready to Pack', 'Submitted by Customer', 'Ready to Ship', 'Ready to Pick', 'Packing', 'Packed', 'Packed Done' => TransactionStatusEnum::PROCESSING,
                'Suspended', 'Dispatched', 'Unknown', 'Cancelled', 'No Picked Due No Authorised', 'No Picked Due Not Found', 'No Picked Due Other', 'No Picked Due Out of Stock' => TransactionStatusEnum::SETTLED,
            };

            $failStatus = match ($this->auroraModelData->{'Current Dispatching State'}) {
                'No Picked Due Out of Stock' => TransactionFailStatusEnum::OUT_OF_STOCK,
                'No Picked Due No Authorised' => TransactionFailStatusEnum::NO_AUTHORISED,
                'No Picked Due Not Found' => TransactionFailStatusEnum::NOT_FOUND,
                'No Picked Due Other' => TransactionFailStatusEnum::OTHER,
                default => null,
            };

            $date = $this->parseDatetime($this->auroraModelData->{'Order Date'});
            $date = new Carbon($date);

            $this->parsedData['historic_asset'] = $historicAsset;

            $quantityFail = round($this->auroraModelData->{'No Shipped Due Out of Stock'}, 4);
            if ($quantityFail < 0.001) {
                $quantityFail = 0;
            }

            $quantityBonus = round($this->auroraModelData->{'Order Bonus Quantity'}, 4);
            if ($quantityBonus < 0.001) {
                $quantityBonus = 0;
            }

            $taxCategory = $this->parseTaxCategory($this->auroraModelData->{'Order Transaction Tax Category Key'});

            $dispatchedQuantity = $this->auroraModelData->{'Delivery Note Quantity'};
            if ($this->auroraModelData->{'Order Transaction Product Type'} == 'Service') {
                $dispatchedQuantity = $this->auroraModelData->{'Order Quantity'};
            }
            $this->parsedData['transaction'] = [
                'date' => $date,
                'created_at' => $date,
                'tax_category_id' => $taxCategory->id,
                'state' => $state,
                'status' => $status,
                'quantity_ordered' => $this->auroraModelData->{'Order Quantity'},
                'quantity_bonus' => $quantityBonus,
                'quantity_dispatched' => $dispatchedQuantity,
                'quantity_fail' => $quantityFail,
                'gross_amount' => $this->auroraModelData->{'Order Transaction Gross Amount'},
                'net_amount' => $this->auroraModelData->{'Order Transaction Amount'},
                'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Order Transaction Fact Key'},
                'fail_status' => $failStatus,
                'fetched_at' => now(),
                'last_fetched_at' => now(),

            ];
        } else {
            echo 'Warning Asset Key missing in transaction >'.$this->auroraModelData->{'Order Transaction Fact Key'}."\n";
        }
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Order Transaction Fact')
            ->where('Order Transaction Fact Key', $id)->first();
    }
}
