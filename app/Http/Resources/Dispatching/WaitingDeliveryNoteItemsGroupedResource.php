<?php

/*
 * Author: Vika Aqordi
 * Created on 09-04-2026-10h-52m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Http\Resources\Dispatching;

use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
 */
class WaitingDeliveryNoteItemsGroupedResource extends JsonResource
{
    public function toArray($request): array
    {
        $deliveryNote = DeliveryNote::find($this->delivery_note_id);

        $items = DB::table('delivery_note_items')
            ->leftJoin('org_stocks', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id')
            ->where('delivery_note_items.delivery_note_id', $this->delivery_note_id)
            ->where('delivery_note_items.state', DeliveryNoteItemStateEnum::HANDLING_BLOCKED->value)
            ->select([
                'delivery_note_items.id',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'delivery_note_items.quantity_required',
                'delivery_note_items.quantity_picked',
                DB::raw('(delivery_note_items.quantity_required - COALESCE(delivery_note_items.quantity_picked, 0)) as quantity_waiting'),
            ])
            ->get()
            ->toArray();

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
            'items'                             => $items,
        ];
    }
}
