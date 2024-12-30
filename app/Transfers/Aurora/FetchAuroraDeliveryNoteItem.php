<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

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

        $this->parsedData['type'] = $this->auroraModelData->{'Inventory Transaction Type'};

        $state = match ($deliveryNote->state) {
            DeliveryNoteStateEnum::UNASSIGNED => DeliveryNoteItemStateEnum::UNASSIGNED,
            DeliveryNoteStateEnum::QUEUED => DeliveryNoteItemStateEnum::QUEUED,
            DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED => DeliveryNoteItemStateEnum::HANDLING,
            DeliveryNoteStateEnum::PACKED => DeliveryNoteItemStateEnum::PACKED,
            DeliveryNoteStateEnum::FINALISED => DeliveryNoteItemStateEnum::FINALISED,
            DeliveryNoteStateEnum::DISPATCHED => DeliveryNoteItemStateEnum::DISPATCHED,
            DeliveryNoteStateEnum::CANCELLED => DeliveryNoteItemStateEnum::CANCELLED,
        };


        $quantity_required   = $this->auroraModelData->{'Required'};
        $quantity_dispatched = -$this->auroraModelData->{'Inventory Transaction Quantity'};


        $transactionID = $transaction?->id;

        $stock = null;

        if ($orgStock) {
            $stock = Stock::withTrashed()->find($orgStock->stock_id);
        }

        $this->parsedData['delivery_note_item'] = [
            'transaction_id'      => $transactionID,
            'state'               => $state,
            'quantity_required'   => $quantity_required,
            'quantity_picked'     => $this->auroraModelData->{'Picked'},
            'quantity_packed'     => $this->auroraModelData->{'Packed'},
            'quantity_dispatched' => $quantity_dispatched,
            'source_id'           => $this->organisation->id.':'.$this->auroraModelData->{'Inventory Transaction Key'},
            'created_at'          => $this->auroraModelData->{'Date Created'},
            'fetched_at'          => now(),
            'last_fetched_at'     => now(),
            'org_stock_id'        => $orgStock?->id,
            'org_stock_family_id' => $orgStock?->org_stock_family_id,
            'stock_id'            => $stock ? $stock->id : null,
            'stock_family_id'     => $stock ? $stock->stock_family_id : null


        ];
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
