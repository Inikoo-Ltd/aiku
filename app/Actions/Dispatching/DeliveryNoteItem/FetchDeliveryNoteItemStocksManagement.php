<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created: Fri, 10 Jul 2026 10:00:00 Bali, Indonesia
 * Copyright (c) 2026, Vika Aqordi
*/

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockShowcase;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * Returns the stocks management showcase payload for a delivery note item's org stock,
 * so the picker can move/manage stock across locations without leaving the picking screen.
 */
class FetchDeliveryNoteItemStocksManagement extends OrgAction
{
    public function handle(DeliveryNoteItem $deliveryNoteItem): Collection
    {
        return GetOrgStockShowcase::run($deliveryNoteItem->deliveryNote->warehouse, $deliveryNoteItem->orgStock);  // Should takes only stocks management (not enough time)
    }

    public function jsonResponse(Collection $showcase): array
    {
        return $showcase->toArray();
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem);
    }
}
