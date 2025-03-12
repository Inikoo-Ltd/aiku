<?php

/*
 * author Arya Permana - Kirin
 * created on 04-03-2025-15h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote\UI;

trait WithDeliveryNotesSubNavigation
{
    protected function getDeliveryNotesSubNavigation(): array
    {
        $organisation = $this->organisation;

        return [


            [
                'isAnchor' => true,
                'label'    => __('All'),

                'route'     => [
                    'name'       => 'grp.org.warehouses.show.dispatching.delivery-notes',
                    'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                ],
                'number'   => $organisation->orderingStats->number_delivery_notes
            ],
            [
                'label'    => __('Dispatched'),

                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.dispatched.delivery-notes',
                    'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                ],
                'number'   => $organisation->orderingStats->number_delivery_notes_state_dispatched
            ],

            [
                'align' => 'right',
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.unassigned.delivery-notes',
                    'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                ],
                'label'    => __('To do'),
                'number'   => $organisation->orderingStats->number_delivery_notes_state_unassigned
            ],
            [
                'align' => 'right',
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.queued.delivery-notes',
                    'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                ],
                'label'    => __('Queued'),
                'number'   => $organisation->orderingStats->number_delivery_notes_state_queued
            ],
            [
                'align' => 'right',
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.handling.delivery-notes',
                    'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                ],
                'label'    => __('Handling'),
                'number'   => $organisation->orderingStats->number_delivery_notes_state_handling
            ],
            [
                'align' => 'right',
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes',
                    'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                ],
                'label'    => __('Handling Blocked'),
                'number'   => $organisation->orderingStats->number_delivery_notes_state_handling_blocked
            ],
            [
                'align' => 'right',
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.packed.delivery-notes',
                    'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                ],
                'label'    => __('Packed'),
                'number'   => $organisation->orderingStats->number_delivery_notes_state_packed
            ],
            [
                'align' => 'right',
                'route' => [
                    'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes',
                    'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                ],
                'label'    => __('Finalised'),
                'number'   => $organisation->orderingStats->number_delivery_notes_state_finalised
            ],



        ];
    }
}
