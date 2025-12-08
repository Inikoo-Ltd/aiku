<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Mar 2025 19:06:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp\Layout;

use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAgentWarehouseNavigation
{
    use AsAction;
    use WithLayoutNavigation;

    public function handle(Warehouse $warehouse, User $user): array
    {
        $navigation = [];

        if ($user->hasAnyPermission([
            "inventory.{$warehouse->organisation->id}.view",
            "stocks.$warehouse->id.view",
            "fulfilment.$warehouse->id.view",
        ])) {
            $navigation['inventory'] = [
                'root' => 'grp.org.warehouses.show.agent_inventory.',
                'label' => __('inventory'),
                'icon' => ['fal', 'fa-pallet-alt'],
                'route' => [
                    'name' => 'grp.org.warehouses.show.agent_inventory.dashboard',
                    'parameters' => [$warehouse->organisation->slug, $warehouse->slug],
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'icon' => ['fal', 'fa-chart-network'],
                            'root' => 'grp.org.warehouses.show.agent_inventory.dashboard',
                            'route' => [
                                'name' => 'grp.org.warehouses.show.agent_inventory.dashboard',
                                'parameters' => [$warehouse->organisation->slug, $warehouse->slug],
                            ],
                        ],

                        $user->hasAnyPermission([
                            "inventory.{$warehouse->organisation->id}.view",
                            "stocks.$warehouse->id.view",
                        ])
                            ? [
                                'label' => __('SKUs'),
                                'icon' => ['fal', 'fa-box'],
                                'root' => 'grp.org.warehouses.show.agent_inventory.supplier_products.',
                                'route' => [
                                    'name' => 'grp.org.warehouses.show.agent_inventory.supplier_products.index',
                                    'parameters' => [$warehouse->organisation->slug, $warehouse->slug],
                                ],
                            ] : null,

                    ],
                ],
            ];
        }

        $navigation = $this->getLocationsNavs($user, $warehouse, $navigation);

        if ($user->hasAnyPermission([
            "incoming.$warehouse->id.view",
        ])) {
            $navigation['incoming'] = [
                'root' => 'grp.org.warehouses.show.agent_incoming.',
                'label' => __('Goods in'),
                'icon' => ['fal', 'fa-arrow-to-bottom'],
                'route' => [
                    'name' => 'grp.org.warehouses.show.agent_incoming.backlog',
                    'parameters' => [
                        $warehouse->organisation->slug,
                        $warehouse->slug,
                    ],
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'icon' => ['fal', 'fa-tasks-alt'],
                            'root' => 'grp.org.warehouses.show.agent_incoming.backlog',
                            'route' => [
                                'name' => 'grp.org.warehouses.show.agent_incoming.backlog',
                                'parameters' => [
                                    $warehouse->organisation->slug,
                                    $warehouse->slug,
                                ],
                            ],
                        ],
                        $user->hasPermissionTo("incoming.$warehouse->id.view") ?
                            [
                                'label' => __('stock deliveries'),
                                'icon' => ['fal', 'fa-truck-container'],
                                'root' => 'grp.org.warehouses.show.agent_incoming.stock_deliveries.',
                                'route' => [
                                    'name' => 'grp.org.warehouses.show.agent_incoming.stock_deliveries.index',
                                    'parameters' => [
                                        $warehouse->organisation->slug,
                                        $warehouse->slug,
                                    ],
                                ],
                            ] : null,
                        $user->hasPermissionTo("fulfilment.$warehouse->id.view") ?
                            [
                                'label' => __('fulfilment deliveries'),
                                'icon' => ['fal', 'fa-truck-couch'],
                                'root' => 'grp.org.warehouses.show.agent_incoming.pallet_deliveries.',
                                'route' => [
                                    'name' => 'grp.org.warehouses.show.agent_incoming.pallet_deliveries.index',
                                    'parameters' => [
                                        $warehouse->organisation->slug,
                                        $warehouse->slug,
                                    ],
                                ],
                            ] : null,

                    ],
                ],
            ];
        }

        if ($user->hasAnyPermission(["dispatching.$warehouse->id.view"])) {
            $navigation['dispatching'] = [
                'root' => 'grp.org.warehouses.show.agent_dispatching.',
                'label' => __('Goods out'),
                'icon' => ['fal', 'fa-arrow-from-left'],
                'route' => [
                    'name' => 'grp.org.warehouses.show.agent_dispatching.backlog',
                    'parameters' => [
                        $warehouse->organisation->slug,
                        $warehouse->slug,
                    ],
                ],
                'topMenu' => [
                    'subSections' => [
                        [
                            'icon' => ['fal', 'fa-tasks-alt'],
                            'root' => 'grp.org.warehouses.show.agent_dispatching.backlog',
                            'route' => [
                                'name' => 'grp.org.warehouses.show.agent_dispatching.backlog',
                                'parameters' => [
                                    $warehouse->organisation->slug,
                                    $warehouse->slug,
                                ],
                            ],
                        ],
                        $user->hasPermissionTo("dispatching.$warehouse->id.view") ?
                            [
                                'label' => __('delivery notes'),
                                'icon' => ['fal', 'fa-truck'],
                                'root' => 'grp.org.warehouses.show.agent_dispatching.delivery-notes',
                                'route' => [
                                    'name' => 'grp.org.warehouses.show.agent_dispatching.delivery-notes',
                                    'parameters' => [
                                        $warehouse->organisation->slug,
                                        $warehouse->slug,
                                    ],
                                ],
                            ] : null,
                        $user->hasPermissionTo("fulfilment.$warehouse->id.view") ?
                            [
                                'label' => __('Fulfilment Returns'),
                                'tooltip' => __('Fulfilment returns'),
                                'icon' => 'fal fa-sign-out',
                                'root' => 'grp.org.warehouses.show.agent_dispatching.pallet-returns.',
                                'route' => [
                                    'name' => 'grp.org.warehouses.show.agent_dispatching.pallet-returns.index',
                                    'parameters' => [$warehouse->organisation->slug, $warehouse->slug],
                                ],
                            ] : null,
                    ],
                ],
            ];
        }

        return $navigation;
    }
}
