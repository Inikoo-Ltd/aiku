<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateCompositionDirtyItems;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseManagementEditAuthorisation;
use App\Models\Dispatching\DeliveryNoteItem;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Once a dirty item's picking has been rolled back, the new composition can be applied:
 * required quantity is recomputed against today's pick mapping and the flag cleared.
 * Refused while physical work still stands, rolling that back is the human's job.
 */
class ApplyNewCompositionToDeliveryNoteItem extends OrgAction
{
    use WithWarehouseManagementEditAuthorisation;

    public function handle(DeliveryNoteItem $deliveryNoteItem): DeliveryNoteItem
    {
        if (!in_array($deliveryNoteItem->state, SyncDeliveryNoteItemsRequiredPickQuantity::SYNCED_STATES)
            || SyncDeliveryNoteItemsRequiredPickQuantity::make()->hasPhysicalWork($deliveryNoteItem)) {
            throw new HttpException(422, __('Roll back the picking first: this item is :state and its physical work still stands.', ['state' => $deliveryNoteItem->state->value]));
        }

        $orgStock         = $deliveryNoteItem->orgStock;
        $quantityRequired = SyncDeliveryNoteItemsRequiredPickQuantity::make()->getQuantityRequired($orgStock, $deliveryNoteItem);

        if (!is_null($quantityRequired) && $quantityRequired != $deliveryNoteItem->quantity_required) {
            UpdateDeliveryNoteItem::make()->action($deliveryNoteItem, [
                'quantity_required'          => $quantityRequired,
                'original_quantity_required' => $quantityRequired,
                'estimated_required_weight'  => (int)($quantityRequired * ($orgStock->stock?->gross_weight ?? 0)),
            ], strict: false);
        }

        $deliveryNoteItem->update([
            'composition_dirty_at'                => null,
            'composition_dirty_quantity_required' => null,
        ]);

        DeliveryNoteHydrateCompositionDirtyItems::run($deliveryNoteItem->delivery_note_id);

        return $deliveryNoteItem;
    }

    public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): DeliveryNoteItem
    {
        $this->initialisationFromShop($deliveryNoteItem->shop, $request);

        return $this->handle($deliveryNoteItem);
    }
}
