<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Jun 2025 09:58:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDispatchHubFulfilmentWidget
{
    use AsObject;

    public function handle(Warehouse $warehouse, ActionRequest $request): array
    {

        $todoLabel = __('To do');
        return [
            'label'    => __('Fulfilment'),
            'sublabel' => $todoLabel,
            'count'    => $warehouse->stats->number_pallet_returns_state_confirmed + $warehouse->stats->number_pallet_returns_state_picking + $warehouse->stats->number_pallet_returns_state_picked,
            'cases'    => [
                'todo'    => [
                    'label' => $todoLabel,
                    'key'   => 'todo',
                    'icon'  => 'fal fa-stream',
                    'value' => $warehouse->stats->number_pallet_returns_state_confirmed,
                    'route' => [
                        'name'       => match (class_basename($warehouse)) {
                            'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.new.index',
                            'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.confirmed.index'
                        },
                        'parameters' => match (class_basename($warehouse)) {
                            'Fulfilment' => array_merge($request->route()->originalParameters(), ['returns_elements[state]' => 'confirmed']),
                            'Warehouse' => $request->route()->originalParameters()
                        }

                    ],
                ],
                'picking' => [
                    'label' => __('Picking'),
                    'key'   => 'picking',
                    'icon'  => 'fal fa-check',
                    'value' => $warehouse->stats->number_pallet_returns_state_picking,
                    'route' => [
                        'name'       => match (class_basename($warehouse)) {
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
                    'value' => $warehouse->stats->number_pallet_returns_state_picked,
                    'route' => [
                        'name'       => match (class_basename($warehouse)) {
                            'Fulfilment' => 'grp.org.fulfilments.show.operations.pallet-returns.picked.index',
                            'Warehouse' => 'grp.org.warehouses.show.dispatching.pallet-returns.picked.index'
                        },
                        'parameters' => $request->route()->originalParameters()
                    ],
                ],
            ]
        ];


    }
}
