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
    protected function getDeliveryNotesSubNavigation(string $shopType): array
    {
        $organisation = $this->organisation;

        $isAll = $shopType == 'all';

        return [
                [
                    'align' => 'right',
                    'label' => __('Dispatched'),
                    'route' => $isAll ? [
                        'name'       => 'grp.org.warehouses.show.dispatching.dispatched.delivery-notes',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                    ] : [
                        'name'       => 'grp.org.warehouses.show.dispatching.dispatched.delivery-notes.shop',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug, $shopType]
                    ],
                    'number' => $isAll 
                        ? $organisation->orderingStats->number_delivery_notes_state_dispatched 
                        : $organisation->orderingStats->{'number_'.$shopType.'_shop_delivery_notes_state_dispatched'},
                ],
                [
                    'align' => 'right',
                    'label' => __('All'),
                    'route' => $isAll ? [
                        'name'       => 'grp.org.warehouses.show.dispatching.delivery-notes',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                    ] : [
                        'name'       => 'grp.org.warehouses.show.dispatching.delivery-notes.shop',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug, $shopType]
                    ],
                    'number' => $isAll 
                        ? $organisation->orderingStats->number_delivery_notes 
                        : $organisation->orderingStats->{'number_'.$shopType.'_shop_delivery_notes'},
                ],
                [
                    'label'  => __('To do'),
                    'route'  => $isAll ? [
                        'name'       => 'grp.org.warehouses.show.dispatching.unassigned.delivery-notes',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                    ] : [
                        'name'       => 'grp.org.warehouses.show.dispatching.unassigned.delivery-notes.shop',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug, $shopType]
                    ],
                    'number' => $isAll 
                        ? $organisation->orderingStats->number_delivery_notes_state_unassigned 
                        : $organisation->orderingStats->{'number_'.$shopType.'_shop_delivery_notes_state_unassigned'},
                ],
                [
                    'label'  => __('Queued'),
                    'route'  => $isAll ? [
                        'name'       => 'grp.org.warehouses.show.dispatching.queued.delivery-notes',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                    ] : [
                        'name'       => 'grp.org.warehouses.show.dispatching.queued.delivery-notes.shop',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug, $shopType]
                    ],
                    'number' => $isAll 
                        ? $organisation->orderingStats->number_delivery_notes_state_queued 
                        : $organisation->orderingStats->{'number_'.$shopType.'_shop_delivery_notes_state_queued'},
                ],
                [
                    'label'  => __('Handling'),
                    'route'  => $isAll ? [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling.delivery-notes',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                    ] : [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling.delivery-notes.shop',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug, $shopType]
                    ],
                    'number' => $isAll 
                        ? $organisation->orderingStats->number_delivery_notes_state_handling 
                        : $organisation->orderingStats->{'number_'.$shopType.'_shop_delivery_notes_state_handling'},
                ],
                [
                    'label'  => __('Handling Blocked'),
                    'route'  => $isAll ? [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                    ] : [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug, $shopType]
                    ],
                    'number' => $isAll 
                        ? $organisation->orderingStats->number_delivery_notes_state_handling_blocked 
                        : $organisation->orderingStats->{'number_'.$shopType.'_shop_delivery_notes_state_handling_blocked'},
                ],
                [
                    'label'  => __('Packed'),
                    'route'  => $isAll ? [
                        'name'       => 'grp.org.warehouses.show.dispatching.packed.delivery-notes',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                    ] : [
                        'name'       => 'grp.org.warehouses.show.dispatching.packed.delivery-notes.shop',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug, $shopType]
                    ],
                    'number' => $isAll 
                        ? $organisation->orderingStats->number_delivery_notes_state_packed 
                        : $organisation->orderingStats->{'number_'.$shopType.'_shop_delivery_notes_state_packed'},
                ],
                [
                    'label'  => __('Finalised'),
                    'route'  => $isAll ? [
                        'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                    ] : [
                        'name'       => 'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug, $shopType]
                    ],
                    'number' => $isAll 
                        ? $organisation->orderingStats->number_delivery_notes_state_finalised 
                        : $organisation->orderingStats->{'number_'.$shopType.'_shop_delivery_notes_state_finalised'},
                ],
            ];
    }
}
