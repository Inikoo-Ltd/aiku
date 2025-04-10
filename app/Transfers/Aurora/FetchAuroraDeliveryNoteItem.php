<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Goods\Stock;
use Illuminate\Support\Facades\DB;

class FetchAuroraDeliveryNoteItem extends FetchAurora
{
    protected function parseDeliveryNoteTransaction(DeliveryNote $deliveryNote): void
    {
        $orgStock = null;
        if ($this->auroraModelData->{'Part SKU'}) {
            $orgStock = $this->parseOrgStock($this->organisation->id.':'.$this->auroraModelData->{'Part SKU'});
        }


        $auroraTransaction = $this->organisation->id.':'.$this->auroraModelData->{'Map To Order Transaction Fact Key'};

        $transaction = $this->parseTransaction($auroraTransaction);

        $type = $this->auroraModelData->{'Inventory Transaction Type'};

        $this->parsedData['type'] = $type;


        if ($type == 'Sale') {
            $state = DeliveryNoteItemStateEnum::DISPATCHED;
        } elseif ($type == 'No Dispatched') {
            $state = DeliveryNoteItemStateEnum::OUT_OF_STOCK;
        } elseif ($type == 'FailSale') {
            $state = DeliveryNoteItemStateEnum::CANCELLED;
        } elseif ($type == 'Restock' || $type == 'Adjust') {
            return;
        } elseif ($type == 'Order In Process') {
            $state = match ($deliveryNote->state) {
                DeliveryNoteStateEnum::UNASSIGNED => DeliveryNoteItemStateEnum::UNASSIGNED,
                DeliveryNoteStateEnum::QUEUED => DeliveryNoteItemStateEnum::QUEUED,
                DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED => DeliveryNoteItemStateEnum::HANDLING,
                DeliveryNoteStateEnum::PACKED => DeliveryNoteItemStateEnum::PACKED,
                DeliveryNoteStateEnum::FINALISED => DeliveryNoteItemStateEnum::FINALISED,
                default => null
            };
            if ($state === null && $deliveryNote->state == DeliveryNoteStateEnum::DISPATCHED) {
                $state = DeliveryNoteItemStateEnum::OUT_OF_STOCK;
            } elseif ($state === null && $deliveryNote->state == DeliveryNoteStateEnum::CANCELLED) {
                $state = DeliveryNoteItemStateEnum::CANCELLED;
            } elseif ($state === null) {
                dd($this->auroraModelData, 'XX', $deliveryNote->state);
            }
        } else {
            dd($this->auroraModelData);
        }


        $quantity_required   = $this->auroraModelData->{'Required'};
        $quantity_dispatched = -$this->auroraModelData->{'Inventory Transaction Quantity'};


        $transactionID = $transaction?->id;

        $stock = null;

        if ($orgStock) {
            $stock = Stock::withTrashed()->find($orgStock->stock_id);
        }


        $createdAt = $this->parseDatetime($this->auroraModelData->{'Date Created'});
        if (!$createdAt) {
            $createdAt = $this->parseDatetime($this->auroraModelData->{'Date'});
        }
        if (!$createdAt) {
            $createdAt = $deliveryNote->created_at;
        }

        $weight = $this->auroraModelData->{'Inventory Transaction Weight'};
        $weight = abs($weight);


        $revenueAmount = $this->auroraModelData->{'Amount In'};

        $revenueAmountOrgCurrency   = $revenueAmount * GetHistoricCurrencyExchange::run($deliveryNote->shop->currency, $deliveryNote->organisation->currency, $deliveryNote->date);
        $revenueAmountGroupCurrency = $revenueAmount * GetHistoricCurrencyExchange::run($deliveryNote->shop->currency, $deliveryNote->group->currency, $deliveryNote->date);


        $this->parsedData['delivery_note_item'] = [
            'transaction_id'      => $transactionID,
            'state'               => $state,
            'quantity_required'   => $quantity_required,
            'quantity_picked'     => $this->auroraModelData->{'Picked'},
            'quantity_packed'     => $this->auroraModelData->{'Packed'},
            'quantity_dispatched' => $quantity_dispatched,
            'source_id'           => $this->organisation->id.':'.$this->auroraModelData->{'Inventory Transaction Key'},
            'created_at'          => $createdAt,
            'fetched_at'          => now(),
            'last_fetched_at'     => now(),
            'org_stock_id'        => $orgStock?->id,
            'org_stock_family_id' => $orgStock?->org_stock_family_id,
            'stock_id'            => $stock ? $stock->id : null,
            'stock_family_id'     => $stock ? $stock->stock_family_id : null,
            'weight'              => $weight,
            'date'                => $deliveryNote->date,
            'queued_at'           => $deliveryNote->queued_at,
            'handling_at'         => $deliveryNote->handling_at,
            'handling_blocked_at' => $deliveryNote->handling_blocked_at,
            'packed_at'           => $deliveryNote->packed_at,
            'finalised_at'        => $deliveryNote->finalised_at,
            'dispatched_at'       => $deliveryNote->dispatched_at,
            'cancelled_at'        => $deliveryNote->cancelled_at,
            'start_picking'       => $deliveryNote->start_picking,
            'end_picking'         => $deliveryNote->end_picking,
            'start_packing'       => $deliveryNote->start_packing,
            'end_packing'         => $deliveryNote->end_packing,
            'revenue_amount'      => $revenueAmount,
            'org_revenue_amount'  => $revenueAmountOrgCurrency,
            'grp_revenue_amount'  => $revenueAmountGroupCurrency,
        ];

        if ($transaction) {
            $this->parsedData['delivery_note_item']['order_id']    = $transaction->order_id;
            $this->parsedData['delivery_note_item']['customer_id'] = $transaction->customer_id;
            $this->parsedData['delivery_note_item']['invoice_id']  = $transaction->invoice_id;
        }
    }

    public function fetchDeliveryNoteTransaction(int $id, DeliveryNote $deliveryNote): ?array
    {
        $this->auroraModelData = $this->fetchData($id);

        if ($this->auroraModelData) {
            $this->parseDeliveryNoteTransaction($deliveryNote);
        }

        return $this->parsedData;
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Inventory Transaction Fact')
            ->where('Inventory Transaction Key', $id)->first();
    }
}
