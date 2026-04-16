<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 16:10:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsCrm;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateHandling;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $delivery_note_id
 * @property mixed $delivery_note_slug
 * @property mixed $delivery_note_reference
 * @property mixed $delivery_note_customer_notes
 * @property mixed $delivery_note_public_notes
 * @property mixed $delivery_note_internal_notes
 * @property mixed $delivery_note_shipping_notes
 * @property mixed $delivery_note_is_premium_dispatch
 * @property mixed $delivery_note_has_extra_packing
 * @property mixed $order_id
 * @property mixed $order_slug
 * @property mixed $order_reference
 * @property mixed $shop_slug
 * @property mixed $shop_type
 * @property mixed $shop_engine
 * @property mixed $organisation_slug
 * @property mixed $notes
 */
class WaitingDeliveryNoteItemsCrmGroupedResource extends JsonResource
{
    public function toArray($request): array
    {
        $deliveryNote = DeliveryNote::find($this->delivery_note_id);

        return [
            'delivery_note_id'                  => $this->delivery_note_id,
            'delivery_note_slug'                => $this->delivery_note_slug,
            'delivery_note_reference'           => $this->delivery_note_reference,
            'delivery_note_state_icon'          => $deliveryNote?->state->stateIcon()[$deliveryNote->state->value] ?? null,
            'delivery_note_is_premium_dispatch' => $this->delivery_note_is_premium_dispatch,
            'delivery_note_has_extra_packing'   => $this->delivery_note_has_extra_packing,
            'delivery_note_customer_notes'      => $this->delivery_note_customer_notes,
            'delivery_note_public_notes'        => $this->delivery_note_public_notes,
            'delivery_note_internal_notes'      => $this->delivery_note_internal_notes,
            'delivery_note_shipping_notes'      => $this->delivery_note_shipping_notes,
            'order_id'                          => $this->order_id,
            'order_slug'                        => $this->order_slug,
            'order_reference'                   => $this->order_reference,
            'shop_slug'                         => $this->shop_slug,
            'shop_type'                         => $this->shop_type,
            'shop_engine'                       => $this->shop_engine,
            'organisation_slug'                 => $this->organisation_slug,
            'notes'                             => $this->notes,
            'items'                             => $deliveryNote
                ? collect(DeliveryNoteItemsCrmWaitingResource::collection(
                    IndexDeliveryNoteItemsCrm::run($deliveryNote)
                )->resolve())->map(function ($item) {
                    $item['upsert_picking_route'] = [
                        'name'       => 'grp.models.delivery_note_item.picking.upsert_from_waiting_warehouse',
                        'parameters' => ['deliveryNoteItem' => $item['id']],
                        'method'     => 'post',
                    ];
                    $item['picking_all_route'] = [
                        'name'       => 'grp.models.delivery_note_item.picking_all_from_waiting_warehouse.store',
                        'parameters' => ['deliveryNoteItem' => $item['id']],
                        'method'     => 'post',
                    ];
                    return $item;
                })->toArray()
                : [],
        ];
    }
}
