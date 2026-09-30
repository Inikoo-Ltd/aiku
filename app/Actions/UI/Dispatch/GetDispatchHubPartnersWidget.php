<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 30 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\UI\Dispatch;

use App\Actions\Dispatching\DeliveryNote\UI\WithDeliveryNotesChannel;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubPartnersWidget
{
    use AsObject;
    use WithDeliveryNotesChannel;

    public function handle(Warehouse $warehouse): array
    {
        $organisation    = $warehouse->organisation;
        $routeParameters = [$organisation->slug, $warehouse->slug, self::PARTNERS_CHANNEL];
        $stateCounts     = $this->partnerDeliveryNotesStateCounts($organisation);

        $waitingItems = fn (string $waitingColumn, DeliveryNoteStateEnum $state, string $routeName) => [
            'count' => $this->waitingItemsCount($warehouse, $waitingColumn, $state),
            'route' => [
                'name'       => $routeName,
                'parameters' => $routeParameters,
            ],
        ];

        $caseRouteNames = [
            'todo'             => 'grp.org.warehouses.show.dispatching.unassigned.delivery-notes.shop',
            'queued'           => 'grp.org.warehouses.show.dispatching.queued.delivery-notes.shop',
            'handling'         => 'grp.org.warehouses.show.dispatching.handling.delivery-notes.shop',
            'handling_blocked' => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
            'picked'           => 'grp.org.warehouses.show.dispatching.picked.delivery-notes.shop',
            'packing'          => 'grp.org.warehouses.show.dispatching.packing.delivery-notes.shop',
            'packed'           => 'grp.org.warehouses.show.dispatching.packed.delivery-notes.shop',
            'finalised'        => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
        ];

        $caseStates = [
            'todo'             => DeliveryNoteStateEnum::UNASSIGNED,
            'queued'           => DeliveryNoteStateEnum::QUEUED,
            'handling'         => DeliveryNoteStateEnum::HANDLING,
            'handling_blocked' => DeliveryNoteStateEnum::HANDLING_BLOCKED,
            'picked'           => DeliveryNoteStateEnum::PICKED,
            'packing'          => DeliveryNoteStateEnum::PACKING,
            'packed'           => DeliveryNoteStateEnum::PACKED,
            'finalised'        => DeliveryNoteStateEnum::FINALISED,
        ];

        $caseCounts = array_map(fn (DeliveryNoteStateEnum $state) => $stateCounts[$state->value] ?? 0, $caseStates);

        return [
            'slug'                            => self::PARTNERS_CHANNEL,
            'label'                           => __('Partners'),
            'tooltip'                         => __('Partner Orders Delivery Notes'),
            'total_route'                     => [
                'name'       => 'grp.org.warehouses.show.dispatching.in_warehouse.delivery-notes.shop',
                'parameters' => $routeParameters,
            ],
            'waiting_items_still_picking'     => $waitingItems('has_waiting_warehouse', DeliveryNoteStateEnum::HANDLING, 'grp.org.warehouses.show.dispatching.waiting_items_still_picking.shop'),
            'waiting_items'                   => $waitingItems('has_waiting_warehouse', DeliveryNoteStateEnum::HANDLING_BLOCKED, 'grp.org.warehouses.show.dispatching.waiting_items.shop'),
            'waiting_crm_items_still_picking' => $waitingItems('has_waiting_crm', DeliveryNoteStateEnum::HANDLING, 'grp.org.warehouses.show.dispatching.waiting_crm_items_still_picking.shop'),
            'waiting_crm_items'               => $waitingItems('has_waiting_crm', DeliveryNoteStateEnum::HANDLING_BLOCKED, 'grp.org.warehouses.show.dispatching.waiting_crm_items.shop'),
            'cases'                           => array_map(fn (string $routeName) => [
                'route' => [
                    'name'       => $routeName,
                    'parameters' => $routeParameters,
                ],
            ], $caseRouteNames),
            ...$caseCounts,
            'total'                           => array_sum($caseCounts),
        ];
    }

    private function waitingItemsCount(Warehouse $warehouse, string $waitingColumn, DeliveryNoteStateEnum $state): int
    {
        $query = $warehouse->deliveryNotes()
            ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
            ->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id')
            ->where("delivery_note_items.$waitingColumn", true)
            ->where('delivery_notes.state', $state);

        $this->whereDeliveryNotesChannel($query, self::PARTNERS_CHANNEL);

        return $query->count();
    }
}
