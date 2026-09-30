<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Jun 2025 09:58:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Actions\Dispatching\DeliveryNote\UI\WithDeliveryNotesChannel;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubB2BWidget
{
    use AsObject;
    use WithDeliveryNotesChannel;

    public function handle(Warehouse $warehouse): array
    {
        $organisation       = $warehouse->organisation;
        $partnerStateCounts = $this->partnerDeliveryNotesStateCounts($organisation);
        $count              = fn (string $state) => $this->channelDeliveryNotesCount($organisation, ShopTypeEnum::B2B->value, $state, $partnerStateCounts);

        return [
            'slug'             => 'wholesale',
            'label'            => __('Wholesale'),
            'tooltip'          => __('Wholesale Delivery Notes'),
            'total_route'      => [
                // 'name'       => 'grp.org.warehouses.show.dispatching.delivery-notes.shop',
               'name'       => 'grp.org.warehouses.show.dispatching.in_warehouse.delivery-notes.shop',
                'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
            ],
            'waiting_items_still_picking' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id')
                    ->tap(fn ($query) => $this->whereDeliveryNotesChannel($query, ShopTypeEnum::B2B->value))
                    ->where('delivery_note_items.has_waiting_warehouse', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING)
                    ->count(),
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.waiting_items_still_picking.shop',
                    'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value],
                ],
            ],
            'waiting_items' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id')
                    ->tap(fn ($query) => $this->whereDeliveryNotesChannel($query, ShopTypeEnum::B2B->value))
                    ->where('delivery_note_items.has_waiting_warehouse', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED)
                    ->count(),
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.waiting_items.shop',
                    'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value],
                ],
            ],
            'waiting_crm_items_still_picking' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id')
                    ->tap(fn ($query) => $this->whereDeliveryNotesChannel($query, ShopTypeEnum::B2B->value))
                    ->where('delivery_note_items.has_waiting_crm', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING)
                    ->count(),
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.waiting_crm_items_still_picking.shop',
                    'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value],
                ],
            ],
            'waiting_crm_items' => [
                'count' => $warehouse->deliveryNotes()
                    ->join('delivery_note_items', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
                    ->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id')
                    ->tap(fn ($query) => $this->whereDeliveryNotesChannel($query, ShopTypeEnum::B2B->value))
                    ->where('delivery_note_items.has_waiting_crm', true)
                    ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED)
                    ->count(),
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.waiting_crm_items.shop',
                    'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value],
                ],
            ],
            'cases'            => [
                'todo'             => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.unassigned.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
                'queued'           => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.queued.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
                'handling'         => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
                'handling_blocked' => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
                'picked'           => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.picked.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
                'packing'           => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.packing.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
                'packed'           => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.packed.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
                'finalised'        => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
            ],
            'todo'             => $count('unassigned'),
            'queued'           => $count('queued'),
            'handling'         => $count('handling'),
            'handling_blocked' => $count('handling_blocked'),
            'picked'           => $count('picked'),
            'packing'          => $count('packing'),
            'packed'           => $count('packed'),
            'finalised'        => $count('finalised'),
            'total'            => $count('unassigned')
                                    + $count('queued')
                                    + $count('handling')
                                    + $count('handling_blocked')
                                    + $count('picked')
                                    + $count('packing')
                                    + $count('packed')
                                    + $count('finalised'),
        ];
    }
}
