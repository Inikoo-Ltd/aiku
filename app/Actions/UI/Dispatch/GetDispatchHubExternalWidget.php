<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Feb 2026 12:05:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubExternalWidget
{
    use AsObject;

    public function handle(Warehouse $warehouse): array
    {
        $organisation = $warehouse->organisation;
        return [
            'label'    => __('Marketplaces'),
            'tooltip'    => __('Marketplaces Delivery Notes'),
            'sublabel' => __('In Todo'),
            'count'    => $organisation->orderingStats->number_external_shop_delivery_notes_state_unassigned + $organisation->orderingStats->number_external_shop_delivery_notes_state_queued + $organisation->orderingStats->number_external_shop_delivery_notes_state_handling
                + $organisation->orderingStats->number_external_shop_delivery_notes_state_handling_blocked + $organisation->orderingStats->number_external_shop_delivery_notes_state_packed + $organisation->orderingStats->number_external_shop_delivery_notes_state_finalised,
            'cases'    => [
                'todo'             => [
                    'label' => __('To do'),
                    'key'   => 'todo',
                    'icon'  => 'fal fa-tasks',
                    'value' => $organisation->orderingStats->number_external_shop_delivery_notes_state_unassigned,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.unassigned.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::EXTERNAL->value]
                    ],
                ],
                'queued'           => [
                    'label' => __('Queued'),
                    'key'   => 'queued',
                    'icon'  => 'fal fa-clock',
                    'value' => $organisation->orderingStats->number_external_shop_delivery_notes_state_queued,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.queued.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::EXTERNAL->value]
                    ],
                ],
                'handling'         => [
                    'label' => __('Handling'),
                    'key'   => 'handling',
                    'icon'  => 'fal fa-hands-helping',
                    'value' => $organisation->orderingStats->number_external_shop_delivery_notes_state_handling,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::EXTERNAL->value]
                    ],
                ],
                'handling_blocked' => [
                    'label' => __('Handling Blocked'),
                    'key'   => 'handling_blocked',
                    'icon'  => 'fal fa-ban',
                    'value' => $organisation->orderingStats->number_external_shop_delivery_notes_state_handling_blocked,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::EXTERNAL->value]
                    ],
                ],
                'packed'           => [
                    'label' => __('Packed'),
                    'key'   => 'packed',
                    'icon'  => 'fal fa-box',
                    'value' => $organisation->orderingStats->number_external_shop_delivery_notes_state_packed,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.packed.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::EXTERNAL->value]
                    ],
                ],
                'finalised'        => [
                    'label' => __('Finalised'),
                    'key'   => 'finalised',
                    'icon'  => 'fal fa-check-circle',
                    'value' => $organisation->orderingStats->number_external_shop_delivery_notes_state_finalised,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::EXTERNAL->value]
                    ],
                ],
            ]
        ];



    }
}
