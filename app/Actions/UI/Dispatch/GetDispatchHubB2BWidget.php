<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Jun 2025 09:58:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubB2BWidget
{
    use AsObject;

    public function handle(Warehouse $warehouse): array
    {
        $organisation = $warehouse->organisation;
        return [
            'label'    => __('B2B'),
            'tooltip'    => __('B2B Delivery Notes'),
            'sublabel' => __('In Todo'),
            'count'    => $organisation->orderingStats->number_b2b_shop_delivery_notes_state_unassigned + $organisation->orderingStats->number_b2b_shop_delivery_notes_state_queued + $organisation->orderingStats->number_b2b_shop_delivery_notes_state_handling
                + $organisation->orderingStats->number_b2b_shop_delivery_notes_state_handling_blocked + $organisation->orderingStats->number_b2b_shop_delivery_notes_state_packed + $organisation->orderingStats->number_b2b_shop_delivery_notes_state_finalised,
            'cases'    => [
                'todo'             => [
                    'label' => __('To do'),
                    'key'   => 'todo',
                    'icon'  => 'fal fa-tasks',
                    'value' => $organisation->orderingStats->number_b2b_shop_delivery_notes_state_unassigned,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.unassigned.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
                'queued'           => [
                    'label' => __('Queued'),
                    'key'   => 'queued',
                    'icon'  => 'fal fa-clock',
                    'value' => $organisation->orderingStats->number_b2b_shop_delivery_notes_state_queued,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.queued.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
                'handling'         => [
                    'label' => __('Handling'),
                    'key'   => 'handling',
                    'icon'  => 'fal fa-hands-helping',
                    'value' => $organisation->orderingStats->number_b2b_shop_delivery_notes_state_handling,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
                'handling_blocked' => [
                    'label' => __('Handling Blocked'),
                    'key'   => 'handling_blocked',
                    'icon'  => 'fal fa-ban',
                    'value' => $organisation->orderingStats->number_b2b_shop_delivery_notes_state_handling_blocked,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
                'packed'           => [
                    'label' => __('Packed'),
                    'key'   => 'packed',
                    'icon'  => 'fal fa-box',
                    'value' => $organisation->orderingStats->number_b2b_shop_delivery_notes_state_packed,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.packed.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
                'finalised'        => [
                    'label' => __('Finalised'),
                    'key'   => 'finalised',
                    'icon'  => 'fal fa-check-circle',
                    'value' => $organisation->orderingStats->number_b2b_shop_delivery_notes_state_finalised,
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2B->value]
                    ],
                ],
            ]
        ];



    }
}
