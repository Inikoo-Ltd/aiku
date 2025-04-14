<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Feb 2024 19:57:44 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem\UI;

use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItem;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStoredItemShowcase
{
    use AsObject;

    public function handle(StoredItem $storedItem): array
    {

        $lastAudit = $storedItem->fulfilmentCustomer->storedItemAudits->last();
        return [
            'stored_item'         => StoredItemResource::make($storedItem)->getArray(),
            'pallets'             => $storedItem->pallets->map(function (Pallet $pallet) {
                return [
                    'id'        => $pallet->id,
                    'slug'      => $pallet->slug,
                    'reference' => $pallet->reference,
                    'location'  => [
                        'id'     => $pallet->location?->id,
                        'code'   => $pallet->location?->code,
                        'slug'   => $pallet->location?->slug,
                    ],
                    'quantity' => (int) $pallet->pivot->quantity
                ];
            }),
            'pieData'             => $this->getDashboardData($storedItem),
            'route_pallets'       => [
                'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.index',
                'parameters' => [
                    'organisation'         => $storedItem->organisation->slug,
                    'fulfilment'           => $storedItem->fulfilment->slug,
                    'fulfilmentCustomer'   => $storedItem->fulfilmentCustomer->slug
                ]
            ],
            'last_audit_slug'         => $lastAudit?->slug,
            'route_update_stored_item' => [
                'name'       => 'grp.models.stored-items.pallets.update',
                'parameters' => [
                    'storedItem' => $storedItem->id
                ]
            ]
        ];
    }

    public function getDashboardData(StoredItem $parent): array
    {


        $stats          = [];
        $stats['stats'] = $parent->pallets->map(function (Pallet $pallet) {
            return [
                'label' => $pallet->reference,
                'value' => (int) $pallet->pivot->quantity
            ];
        });

        return $stats;
    }
}
