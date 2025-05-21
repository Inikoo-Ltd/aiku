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

    public function handle(Warehouse $parent, ActionRequest $request): array
    {
        $organisation = $parent->organisation;

        $b2bShops          = $organisation->shops()->where('type', ShopTypeEnum::B2B)->where('state', ShopStateEnum::OPEN);
        $b2cShops          = $organisation->shops()->where('type', ShopTypeEnum::B2C)->where('state', ShopStateEnum::OPEN);
        $dropshippingShops = $organisation->shops()->where('type', ShopTypeEnum::DROPSHIPPING)->where('state', ShopStateEnum::OPEN);
        $fulfilmentShops   = $organisation->shops()->where('type', ShopTypeEnum::FULFILMENT)->where('state', ShopStateEnum::OPEN);

        $stats = [];

        $todoLabel = __('To do');

        if ($fulfilmentShops->exists()) {
            $stats['fulfilment'] = [
                'label'    => __('Fulfilment'),
                'sublabel' => $todoLabel,
                'count'    => $parent->stats->number_pallet_returns_state_confirmed + $parent->stats->number_pallet_returns_state_picking + $parent->stats->number_pallet_returns_state_picked,
                'cases'    => [
                    'todo'    => [
                        'label' => $todoLabel,
                        'key'   => 'todo',
                        'icon'  => 'fal fa-stream',
                        'value' => $parent->stats->number_pallet_returns_state_confirmed,
                        'route' => [
                            'name'       => match (class_basename($parent)) {
                                'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.new.index',
                                'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.confirmed.index'
                            },
                            'parameters' => match (class_basename($parent)) {
                                'Fulfilment' => array_merge($request->route()->originalParameters(), ['returns_elements[state]' => 'confirmed']),
                                'Warehouse' => $request->route()->originalParameters()
                            }

                        ],
                    ],
                    'picking' => [
                        'label' => __('Picking'),
                        'key'   => 'picking',
                        'icon'  => 'fal fa-check',
                        'value' => $parent->stats->number_pallet_returns_state_picking,
                        'route' => [
                            'name'       => match (class_basename($parent)) {
                                'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.picking.index',
                                'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.picking.index'
                            },
                            'parameters' => $request->route()->originalParameters()
                        ],
                    ],
                    'picked'  => [
                        'label' => __('Picked'),
                        'key'   => 'picked',
                        'icon'  => 'fal fa-parking',
                        'value' => $parent->stats->number_pallet_returns_state_picked,
                        'route' => [
                            'name'       => match (class_basename($parent)) {
                                'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.picked.index',
                                'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.picked.index'
                            },
                            'parameters' => $request->route()->originalParameters()
                        ],
                    ],
                ]
            ];
        }

        if ($b2bShops->exists()) {
            $stats['b2b'] = [
                'label'    => __('B2B Delivery Notes'),
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
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::B2B->value]
                        ],
                    ],
                    'queued'           => [
                        'label' => __('Queued'),
                        'key'   => 'queued',
                        'icon'  => 'fal fa-clock',
                        'value' => $organisation->orderingStats->number_b2b_shop_delivery_notes_state_queued,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.queued.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::B2B->value]
                        ],
                    ],
                    'handling'         => [
                        'label' => __('Handling'),
                        'key'   => 'handling',
                        'icon'  => 'fal fa-hands-helping',
                        'value' => $organisation->orderingStats->number_b2b_shop_delivery_notes_state_handling,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.handling.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::B2B->value]
                        ],
                    ],
                    'handling_blocked' => [
                        'label' => __('Handling Blocked'),
                        'key'   => 'handling_blocked',
                        'icon'  => 'fal fa-ban',
                        'value' => $organisation->orderingStats->number_b2b_shop_delivery_notes_state_handling_blocked,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::B2B->value]
                        ],
                    ],
                    'packed'           => [
                        'label' => __('Packed'),
                        'key'   => 'packed',
                        'icon'  => 'fal fa-box',
                        'value' => $organisation->orderingStats->number_b2b_shop_delivery_notes_state_packed,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.packed.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::B2B->value]
                        ],
                    ],
                    'finalised'        => [
                        'label' => __('Finalised'),
                        'key'   => 'finalised',
                        'icon'  => 'fal fa-check-circle',
                        'value' => $organisation->orderingStats->number_b2b_shop_delivery_notes_state_finalised,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::B2B->value]
                        ],
                    ],
                ]
            ];
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
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::B2C->value]
                        ],
                    ],
                    'queued'           => [
                        'label' => __('Queued'),
                        'key'   => 'queued',
                        'icon'  => 'fal fa-clock',
                        'value' => $organisation->orderingStats->number_b2c_shop_delivery_notes_state_queued,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.queued.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::B2C->value]
                        ],
                    ],
                    'handling'         => [
                        'label' => __('Handling'),
                        'key'   => 'handling',
                        'icon'  => 'fal fa-hands-helping',
                        'value' => $organisation->orderingStats->number_b2c_shop_delivery_notes_state_handling,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.handling.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::B2C->value]
                        ],
                    ],
                    'handling_blocked' => [
                        'label' => __('Handling Blocked'),
                        'key'   => 'handling_blocked',
                        'icon'  => 'fal fa-ban',
                        'value' => $organisation->orderingStats->number_b2c_shop_delivery_notes_state_handling_blocked,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::B2C->value]
                        ],
                    ],
                    'packed'           => [
                        'label' => __('Packed'),
                        'key'   => 'packed',
                        'icon'  => 'fal fa-box',
                        'value' => $organisation->orderingStats->number_b2c_shop_delivery_notes_state_packed,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.packed.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::B2C->value]
                        ],
                    ],
                    'finalised'        => [
                        'label' => __('Finalised'),
                        'key'   => 'finalised',
                        'icon'  => 'fal fa-check-circle',
                        'value' => $organisation->orderingStats->number_b2c_shop_delivery_notes_state_finalised,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::B2C->value]
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
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::DROPSHIPPING->value]
                        ],
                    ],
                    'queued'           => [
                        'label' => __('Queued'),
                        'key'   => 'queued',
                        'icon'  => 'fal fa-clock',
                        'value' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_queued,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.queued.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::DROPSHIPPING->value]
                        ],
                    ],
                    'handling'         => [
                        'label' => __('Handling'),
                        'key'   => 'handling',
                        'icon'  => 'fal fa-hands-helping',
                        'value' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_handling,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.handling.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::DROPSHIPPING->value]
                        ],
                    ],
                    'handling_blocked' => [
                        'label' => __('Handling Blocked'),
                        'key'   => 'handling_blocked',
                        'icon'  => 'fal fa-ban',
                        'value' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_handling_blocked,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::DROPSHIPPING->value]
                        ],
                    ],
                    'packed'           => [
                        'label' => __('Packed'),
                        'key'   => 'packed',
                        'icon'  => 'fal fa-box',
                        'value' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_packed,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.packed.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::DROPSHIPPING->value]
                        ],
                    ],
                    'finalised'        => [
                        'label' => __('Finalised'),
                        'key'   => 'finalised',
                        'icon'  => 'fal fa-check-circle',
                        'value' => $organisation->orderingStats->number_dropshipping_shop_delivery_notes_state_finalised,
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
                            'parameters' => [$organisation->slug, $parent->slug, ShopTypeEnum::DROPSHIPPING->value]
                        ],
                    ],
                ]
            ];
        }


        return $stats;
    }
}
