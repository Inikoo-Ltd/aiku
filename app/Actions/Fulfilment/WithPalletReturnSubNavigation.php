<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 May 2024 12:26:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment;

use App\Models\Fulfilment\Fulfilment;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\ActionRequest;

trait WithPalletReturnSubNavigation
{
    public function getPalletReturnSubNavigation(Fulfilment|Warehouse $parent, ActionRequest $request): array
    {
        $subNavigation = [];

        $subNavigation[] = [

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

            'label' => __('To do'),
            'leftIcon' => [
                'icon' => 'fal fa-stream',
                'tooltip' => __('To do'),
            ],
            'number' => $parent->stats->number_pallet_returns_state_confirmed
        ];

        $subNavigation[] = [
            'route' => [
                'name' => match (class_basename($parent)) {
                    'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.picking.index',
                    'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.picking.index'
                },
                'parameters' => $request->route()->originalParameters()
            ],

            'label' => __("Picking"),
            'leftIcon' => [
                'icon' => 'fal fa-parking',
                'tooltip' => __("Picking"),
            ],
            'number' => $parent->stats->number_pallet_returns_state_picking
        ];

        $subNavigation[] = [
            'route' => [
                'name' => match (class_basename($parent)) {
                    'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.picked.index',
                    'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.picked.index'
                },
                'parameters' => $request->route()->originalParameters()
            ],

            'label' => __("Picked"),
            'leftIcon' => [
                'icon' => 'fal fa-parking',
                'tooltip' => __("Picked"),
            ],
            'number' => $parent->stats->number_pallet_returns_state_picked
        ];

        $subNavigation[] = [
            'route' => [
                'name' => match (class_basename($parent)) {
                    'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.dispatched.index',
                    'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.dispatched.index'
                },
                'parameters' => $request->route()->originalParameters()
            ],

            'label' => __("Dispatched"),
            "align"    => "right",
            'leftIcon' => [
                'icon' => 'fal fa-parking',
                'tooltip' => __("Dispatched"),
            ],
            'number' => $parent->stats->number_pallet_returns_state_dispatched
        ];

        $subNavigation[] = [
            'route' => [
                'name' => match (class_basename($parent)) {
                    'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.cancelled.index',
                    'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.cancelled.index'
                },
                'parameters' => $request->route()->originalParameters()
            ],

            'label' => __("Cancelled"),
            "align"    => "right",
            'leftIcon' => [
                'icon' => 'fal fa-parking',
                'tooltip' => __("Cancelled"),
            ],
            'number' => $parent->stats->number_pallet_returns_state_cancel
        ];

        $subNavigation[] = [
            'route' => [
                'name' => match (class_basename($parent)) {
                    'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.index',
                    'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.index'
                },
                'parameters' => $request->route()->originalParameters()
            ],

            'label' => __("All"),
            "align"    => "right",
            'leftIcon' => [
                'icon' => 'fal fa-parking',
                'tooltip' => __("All"),
            ],
            'number' => $parent->stats->number_pallet_returns
        ];

        return $subNavigation;
    }
}
