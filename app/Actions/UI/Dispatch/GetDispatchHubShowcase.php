<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\UI\Dispatch;

use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubShowcase
{
    use AsObject;

    public function handle(Warehouse $parent, ActionRequest $request): array
    {
        $organisation = $parent->organisation;

        $stats = [
            'fulfilment' => [
                'label' => __('Fulfilment'),
                'sublabel' => __('In Todo'),
                'count' => $parent->stats->number_pallet_returns_state_confirmed + $parent->stats->number_pallet_returns_state_picking + $parent->stats->number_pallet_returns_state_picked,
                'cases' => [
                    'todo' => [
                        'label' => __('To do'),
                        'key'   => 'todo',
                        'icon' => 'fal fa-stream',
                        'value' => $parent->stats->number_pallet_returns_state_confirmed,
                        'route' => [
                            'name' => match (class_basename($parent)) {
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
                            'name' => match (class_basename($parent)) {
                                'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.picking.index',
                                'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.picking.index'
                            },
                            'parameters' => $request->route()->originalParameters()
                        ],
                    ],
                    'picked' => [
                        'label' => __('Picked'),
                        'key'   => 'picked',
                        'icon' => 'fal fa-parking',
                        'value' => $parent->stats->number_pallet_returns_state_picked,
                        'route' => [
                            'name' => match (class_basename($parent)) {
                                'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.picked.index',
                                'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.picked.index'
                            },
                            'parameters' => $request->route()->originalParameters()
                        ],
                    ],
                ]
            ],

            'delivery_notes' => [
                'label' => __('Delivery Notes'),
                'sublabel' => __('In Todo'),
                'count' => $organisation->orderingStats->number_delivery_notes_state_unassigned + $organisation->orderingStats->number_delivery_notes_state_queued + $organisation->orderingStats->number_delivery_notes_state_handling + $organisation->orderingStats->number_delivery_notes_state_handling_blocked + $organisation->orderingStats->number_delivery_notes_state_packed + $organisation->orderingStats->number_delivery_notes_state_finalised,
                'cases' => [
                    'todo' => [
                        'label' => __('To do'),
                        'key'   => 'todo',
                        'icon'  => 'fal fa-tasks',
                        'value' => $organisation->orderingStats->number_delivery_notes_state_unassigned,
                        'route' => [
                            'name' => 'grp.org.warehouses.show.dispatching.unassigned.delivery-notes',
                            'parameters' => [$organisation->slug, $parent->slug]
                        ],
                    ],
                    'queued' => [
                        'label' => __('Queued'),
                        'key'   => 'queued',
                        'icon'  => 'fal fa-clock',
                        'value' => $organisation->orderingStats->number_delivery_notes_state_queued,
                        'route' => [
                            'name' => 'grp.org.warehouses.show.dispatching.queued.delivery-notes',
                            'parameters' => [$organisation->slug, $parent->slug]
                        ],
                    ],
                    'handling' => [
                        'label' => __('Handling'),
                        'key'   => 'handling',
                        'icon'  => 'fal fa-hands-helping',
                        'value' => $organisation->orderingStats->number_delivery_notes_state_handling,
                        'route' => [
                            'name' => 'grp.org.warehouses.show.dispatching.handling.delivery-notes',
                            'parameters' => [$organisation->slug, $parent->slug]
                        ],
                    ],
                    'handling_blocked' => [
                        'label' => __('Handling Blocked'),
                        'key'   => 'handling_blocked',
                        'icon'  => 'fal fa-ban',
                        'value' => $organisation->orderingStats->number_delivery_notes_state_handling_blocked,
                        'route' => [
                            'name' => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes',
                            'parameters' => [$organisation->slug, $parent->slug]
                        ],
                    ],
                    'packed' => [
                        'label' => __('Packed'),
                        'key'   => 'packed',
                        'icon'  => 'fal fa-box',
                        'value' => $organisation->orderingStats->number_delivery_notes_state_packed,
                        'route' => [
                            'name' => 'grp.org.warehouses.show.dispatching.packed.delivery-notes',
                            'parameters' => [$organisation->slug, $parent->slug]
                        ],
                    ],
                    'finalised' => [
                        'label' => __('Finalised'),
                        'key'   => 'finalised',
                        'icon'  => 'fal fa-check-circle',
                        'value' => $organisation->orderingStats->number_delivery_notes_state_finalised,
                        'route' => [
                            'name' => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes',
                            'parameters' => [$organisation->slug, $parent->slug]
                        ],
                    ],
                ]
            ],

        ];


        return $stats;
    }
}
