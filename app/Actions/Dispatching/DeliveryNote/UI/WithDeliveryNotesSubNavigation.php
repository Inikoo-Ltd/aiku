<?php

/*
 * author Arya Permana - Kirin
 * created on 04-03-2025-15h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote\UI;

use App\Enums\Catalogue\Shop\ShopTypeEnum;

trait WithDeliveryNotesSubNavigation
{
    use WithDeliveryNotesChannel;

    protected function getDeliveryNotesSubNavigation(string $shopType): array
    {
        $organisation = $this->organisation;

        $isAll              = $shopType == 'all';
        $partnerStateCounts = in_array($shopType, [self::PARTNERS_CHANNEL, ShopTypeEnum::B2B->value], true) ? $this->partnerDeliveryNotesStateCounts($organisation) : [];
        $count              = fn (?string $state = null) => $this->channelDeliveryNotesCount($organisation, $shopType, $state, $partnerStateCounts);

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
                    'number' => $count('dispatched'),
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
                    'number' => $count(),
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
                    'number' => $count('unassigned'),
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
                    'number' => $count('queued'),
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
                    'number' => $count('handling'),
                ],
                [
                    'label'  => __('Waiting'),
                    'route'  => $isAll ? [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                    ] : [
                        'name'       => 'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug, $shopType]
                    ],
                    'number' => $count('handling_blocked'),
                ],
                [
                    'label'  => __('Picked'),
                    'route'  => $isAll ? [
                        'name'       => 'grp.org.warehouses.show.dispatching.picked.delivery-notes',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                    ] : [
                        'name'       => 'grp.org.warehouses.show.dispatching.picked.delivery-notes.shop',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug, $shopType]
                    ],
                    'number' => $count('picked'),
                ],
                [
                    'label'  => __('Packing'),
                    'route'  => $isAll ? [
                        'name'       => 'grp.org.warehouses.show.dispatching.packing.delivery-notes',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug]
                    ] : [
                        'name'       => 'grp.org.warehouses.show.dispatching.packing.delivery-notes.shop',
                        'parameters' => [$this->organisation->slug, $this->warehouse->slug, $shopType]
                    ],
                    'number' => $count('packing'),
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
                    'number' => $count('packed'),
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
                    'number' => $count('finalised'),
                ],
            ];
    }
}
