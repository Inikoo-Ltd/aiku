<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\UI\Dispatch;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubShowcase
{
    use AsObject;

    public function handle(Warehouse $warehouse, ActionRequest $request): array
    {
        $organisation = $warehouse->organisation;
        $organisationCatalogueStats = $organisation->catalogueStats;

        $b2bShops          = $organisation->shops()->where('type', ShopTypeEnum::B2B)->where('state', ShopStateEnum::OPEN);
        $b2cShops          = $organisation->shops()->where('type', ShopTypeEnum::B2C)->where('state', ShopStateEnum::OPEN);
        $dropshippingShops = $organisation->shops()->where('type', ShopTypeEnum::DROPSHIPPING)->where('state', ShopStateEnum::OPEN);

        $stats = [];


        if ($organisationCatalogueStats->number_shops_type_fulfilment) {
            $stats['fulfilment'] = GetDispatchHubFulfilmentWidget::run($warehouse, $request);
        }

        if ($organisationCatalogueStats->number_shops_type_b2b) {
            $stats['b2b'] = GetDispatchHubFulfilmentWidget::run($warehouse, $request);
        }

        if ($b2bShops->exists()) {
            $stats['b2b'] = GetDispatchHubB2BWidget::run($warehouse);

        }

        if ($b2cShops->exists()) {
            $stats['b2b'] = $stats['b2c'] = [
                'label'    => __('B2C Delivery Notes'),
                'sublabel' => __('In Todo'),
                'count'    => $organisation->orderingStats->number_b2c_shop_delivery_notes_state_unassigned + $organisation->orderingStats->number_b2c_shop_delivery_notes_state_queued + $organisation->orderingStats->number_b2c_shop_delivery_notes_state_handling
                    + $organisation->orderingStats->number_b2c_shop_delivery_notes_state_handling_blocked + $organisation->orderingStats->number_b2c_shop_delivery_notes_state_packed + $organisation->orderingStats->number_b2c_shop_delivery_notes_state_finalised,
                'cases'    => [
                    'todo'             => [
                        'label' => __('To do'),
                        'key'   => 'todo',
                        'icon'  => 'fal fa-tasks',
                        'value' => $organisation->orderingStats->number_b2c_shop_delivery_notes_state_unassigned,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.unassigned.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2C->value]
                        ],
                    ],
                    'queued'           => [
                        'label' => __('Queued'),
                        'key'   => 'queued',
                        'icon'  => 'fal fa-clock',
                        'value' => $organisation->orderingStats->number_b2c_shop_delivery_notes_state_queued,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.queued.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2C->value]
                        ],
                    ],
                    'handling'         => [
                        'label' => __('Handling'),
                        'key'   => 'handling',
                        'icon'  => 'fal fa-hands-helping',
                        'value' => $organisation->orderingStats->number_b2c_shop_delivery_notes_state_handling,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.handling.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2C->value]
                        ],
                    ],
                    'handling_blocked' => [
                        'label' => __('Handling Blocked'),
                        'key'   => 'handling_blocked',
                        'icon'  => 'fal fa-ban',
                        'value' => $organisation->orderingStats->number_b2c_shop_delivery_notes_state_handling_blocked,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2C->value]
                        ],
                    ],
                    'packed'           => [
                        'label' => __('Packed'),
                        'key'   => 'packed',
                        'icon'  => 'fal fa-box',
                        'value' => $organisation->orderingStats->number_b2c_shop_delivery_notes_state_packed,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.packed.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2C->value]
                        ],
                    ],
                    'finalised'        => [
                        'label' => __('Finalised'),
                        'key'   => 'finalised',
                        'icon'  => 'fal fa-check-circle',
                        'value' => $organisation->orderingStats->number_b2c_shop_delivery_notes_state_finalised,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::B2C->value]
                        ],
                    ],
                ]
            ];
        }

        if ($dropshippingShops->exists()) {
            $stats['dropshipping'] = [
                'label'    => __('Dropshipping Delivery Notes'),
                'sublabel' => __('In Todo'),
                'count'    => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_unassigned + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_queued
                    + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_handling + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_handling_blocked
                    + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_packed + $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_finalised,
                'cases'    => [
                    'todo'             => [
                        'label' => __('To do'),
                        'key'   => 'todo',
                        'icon'  => 'fal fa-tasks',
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
                        'value' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_finalised,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $warehouse->slug, ShopTypeEnum::DROPSHIPPING->value]
                        ],
                    ],
                ]
            ];
        }


        return $stats;
    }
}
