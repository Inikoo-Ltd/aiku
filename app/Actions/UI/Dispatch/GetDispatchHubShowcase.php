<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 */

namespace App\Actions\UI\Dispatch;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubShowcase
{
    use AsObject;

    public function handle(Warehouse $warehouse): array
    {
        $organisation = $warehouse->organisation;
        $stats        = $organisation->stats;

        return [
            ...($stats->has_fulfilment ? GetDispatchHubFulfilmentWidget::run($warehouse) : []),
            GetDispatchHubB2BWidget::run($warehouse),
            ...($stats->has_marketplace ? [GetDispatchHubExternalWidget::run($warehouse)] : []),
            GetDispatchHubB2CWidget::run($warehouse),
            ...($stats->has_dropshipping ? [GetDispatchHubDropshippingWidget::run($warehouse)] : []),
            'waiting_items_still_picking' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->where('delivery_note_items.has_waiting_warehouse', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING)
                    ->count(),
                'route' => [
                    'name' => 'grp.org.warehouses.show.dispatching.waiting_items_still_picking',
                    'parameters' => request()->route()->originalParameters()
                ],
            ],
            'waiting_items' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->where('delivery_note_items.has_waiting_warehouse', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED)
                    ->count(),
                'route' => [
                    'name' => 'grp.org.warehouses.show.dispatching.waiting_items',
                    'parameters' => request()->route()->originalParameters()
                ],
            ],
            'waiting_crm_items_still_picking' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->where('delivery_note_items.has_waiting_crm', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING)
                    ->count(),
                'route' => [
                    'name' => 'grp.org.warehouses.show.dispatching.waiting_crm_items_still_picking',
                    'parameters' => request()->route()->originalParameters()
                ],
            ],
            'waiting_crm_items' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->where('delivery_note_items.has_waiting_crm', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED)
                    ->count(),
                'route' => [
                    'name' => 'grp.org.warehouses.show.dispatching.waiting_crm_items',
                    'parameters' => request()->route()->originalParameters()
                ],
            ],
        ];
    }
}
