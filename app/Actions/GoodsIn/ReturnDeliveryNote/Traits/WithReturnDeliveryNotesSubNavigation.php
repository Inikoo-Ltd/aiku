<?php

/*
 * author Louis Perez
 * created on 15-05-2026-10h-35m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote\Traits;

use App\Models\Catalogue\Shop;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;

trait WithReturnDeliveryNotesSubNavigation
{
    protected function getReturnDeliveryNotesSubNavigation(Warehouse|Shop $parent): array
    {
        /** @var Organisation $this->organisation */
        $organisation = $this->organisation;

        return [
            [
                'label'  => __('Received (To do)'),
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.incoming.return_delivery_notes.state.received',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->warehouse->slug
                    ]
                ],
                'number' => $parent->stats->number_return_delivery_notes_state_received,
            ],
            [
                'label'  => __('Returning'),
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.incoming.return_delivery_notes.state.returning',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->warehouse->slug
                    ]
                ],
                'number' => $parent->stats->number_return_delivery_notes_state_returning,
            ],
            [
                'label'  => __('Returned'),
                'route'  => [
                    'name'       => 'grp.org.warehouses.show.incoming.return_delivery_notes.state.returned',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->warehouse->slug
                    ]
                ],
                'number' => $parent->stats->number_return_delivery_notes_state_returned,
            ],
            [
                'align' => 'right',
                'label' => __('Processed'),
                'route' => [
                    'name'       => 'grp.org.warehouses.show.incoming.return_delivery_notes.state.processed',
                    'parameters' => [
                        $organisation->slug,
                        $this->warehouse->slug
                    ]
                ],
                'number' => $parent->stats->number_return_delivery_notes_state_done,
            ],
            [
                'align' => 'right',
                'label' => __('All'),
                'route' => [
                    'name'       => 'grp.org.warehouses.show.incoming.return_delivery_notes.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->warehouse->slug
                    ]
                ],
                'number' => $parent->stats->number_return_delivery_notes,
            ],
        ];
    }
}
