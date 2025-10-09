<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Jun 2025 18:37:51 Central Indonesia Time, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubDropshippingWidget
{
    use AsObject;

    public function handle(Warehouse $warehouse): array
    {
        $organisation = $warehouse->organisation;
        return [
            'label'    => __('Dropshipping'),
            'tooltip'    => __('Dropshipping Delivery Notes'),
            'sublabel' => __('In Todo'),
            'count'    => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_unassigned + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_queued + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_handling
                + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_handling_blocked + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_packed + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_finalised,
            'cases'    => [
                'todo'             => [
                    'label' => __('To do'),
                    'key'   => 'todo',
                    'icon'  => 'fal fa-tasks',
                    'icon_state' => DeliveryNoteStateEnum::stateIcon()[DeliveryNoteStateEnum::UNASSIGNED->value],
                    'value' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_unassigned,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.unassigned.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                    ],
                ],
                'queued'           => [
                    'label' => __('Queued'),
                    'key'   => 'queued',
                    'icon'  => 'fal fa-clock',
                    'icon_state' => DeliveryNoteStateEnum::stateIcon()[DeliveryNoteStateEnum::QUEUED->value],
                    'value' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_queued,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.queued.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                    ],
                ],
                'handling'         => [
                    'label' => __('Handling'),
                    'key'   => 'handling',
                    'icon'  => 'fal fa-hands-helping',
                    'icon_state' => DeliveryNoteStateEnum::stateIcon()[DeliveryNoteStateEnum::HANDLING->value],
                    'value' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_handling,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                    ],
                ],
                'handling_blocked' => [
                    'label' => __('Handling Blocked'),
                    'key'   => 'handling_blocked',
                    'icon'  => 'fal fa-ban',
                    'icon_state' => DeliveryNoteStateEnum::stateIcon()[DeliveryNoteStateEnum::HANDLING_BLOCKED->value],
                    'value' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_handling_blocked,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                    ],
                ],
                'packed'           => [
                    'label' => __('Packed'),
                    'key'   => 'packed',
                    'icon'  => 'fal fa-box',
                    'icon_state' => DeliveryNoteStateEnum::stateIcon()[DeliveryNoteStateEnum::PACKED->value],
                    'value' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_packed,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.packed.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                    ],
                ],
                'finalised'        => [
                    'label' => __('Finalised'),
                    'key'   => 'finalised',
                    'icon'  => 'fal fa-check-circle',
                    'icon_state' => DeliveryNoteStateEnum::stateIcon()[DeliveryNoteStateEnum::FINALISED->value],
                    'value' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_finalised,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                    ],
                ],
            ]
        ];



    }
}
