<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\PickingSession\Traits;

trait WithPickingSessionsSubNavigation
{
    public function getSubNavigation(): array
    {
        $warehouseStats = $this->warehouse->stats;

        return [
            [
                'label'    => __('In Process'),
                'number'   => $warehouseStats->number_picking_sessions_state_in_process,
                'route'    => [
                    'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.in_process',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => $this->warehouse->slug,
                    ],
                ],
                'leftIcon' => [
                    'tooltip' => __('In Process'),
                    'icon'    => 'fal fa-chair',
                ],
            ],
            [
                'label'    => __('Picking'),
                'number'   => $warehouseStats->number_picking_sessions_state_handling,
                'route'    => [
                    'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.picking',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => $this->warehouse->slug,
                    ],
                ],
                'leftIcon' => [
                    'tooltip' => __('Picking'),
                    'icon'    => 'fal fa-hand-paper',
                ],
            ],
            [
                'label'    => __('Waiting'),
                'number'   => $warehouseStats->number_picking_sessions_state_handling_blocked,
                'route'    => [
                    'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.waiting',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => $this->warehouse->slug,
                    ],
                ],
                'leftIcon' => [
                    'tooltip' => __('Waiting'),
                    'icon'    => 'fal fa-hand-paper',
                ],
            ],
            [
                'label'    => __('Picked'),
                'number'   => $warehouseStats->number_picking_sessions_state_picking_finished,
                'route'    => [
                    'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.picked',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => $this->warehouse->slug,
                    ],
                ],
                'leftIcon' => [
                    'tooltip' => __('Picked'),
                    'icon'    => 'fal fa-box-check',
                ],
            ],
            [
                'label'    => __('Packed'),
                'number'   => $warehouseStats->number_picking_sessions_state_packing_finished,
                'route'    => [
                'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.packed',
                'parameters' => [
                    'organisation' => $this->organisation->slug,
                    'warehouse'    => $this->warehouse->slug,
                ],
                ],
                'leftIcon' => [
                    'tooltip' => __('Packed'),
                    'icon'    => 'fal fa-box-check',
                ],
            ],
            [
                'label'    => __('All'),
                'number'   => $warehouseStats->number_picking_sessions,
                'align'    => 'right',
                'route'    => [
                    'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.index',
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'warehouse'    => $this->warehouse->slug,
                    ],
                ],
                'leftIcon' => [
                    'tooltip' => __('All'),
                    'icon'    => 'fal fa-stream',
                ],
            ],
        ];
    }
}
