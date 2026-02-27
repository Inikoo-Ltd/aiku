<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Jun 2025 18:37:51 Central Indonesia Time, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubDropshippingWidget
{
    use AsObject;

    public function handle(Warehouse $warehouse): array
    {
        $organisation = $warehouse->organisation;

        return [
            'label'            => __('Dropshipping'),
            'tooltip'          => __('Dropshipping Delivery Notes'),
            'cases'            => [
                'todo'             => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.unassigned.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                    ],
                ],
                'queued'           => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.queued.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                    ],
                ],
                'handling'         => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                    ],
                ],
                'handling_blocked' => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                    ],
                ],
                'packed'           => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.packed.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                    ],
                ],
                'finalised'        => [
                    'route' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
                        'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                    ],
                ],
            ],
            'todo'             => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_unassigned,
            'queued'           => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_queued,
            'handling'         => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_handling,
            'handling_blocked' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_handling_blocked,
            'packed'           => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_packed,
            'finalised'        => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_finalised,
            'total'            => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_unassigned
                                    + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_queued
                                    + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_handling
                                    + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_handling_blocked
                                    + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_packed
                                    + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_finalised,
        ];
    }
}
