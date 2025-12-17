<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:55:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem\Search;

use App\Models\Fulfilment\StoredItem;
use Lorisleiva\Actions\Concerns\AsAction;

class StoredItemRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(StoredItem $storedItem): void
    {
        $storedItem->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $storedItem->group_id,
                'organisation_id'   => $storedItem->organisation_id,
                'organisation_slug' => $storedItem->organisation->slug,
                'fulfilment_id'     => $storedItem->fulfilment_id,
                'fulfilment_slug'   => $storedItem->fulfilment->slug,
                'sections'          => ['fulfilment'],
                'haystack_tier_1'   => trim($storedItem->reference . ' ' .$storedItem->fulfilmentCustomer->customer->name),
                'haystack_tier_2'   => $storedItem->notes,
                'keyword'           => $storedItem->slug,
                'keyword_2'         => trim($storedItem->reference),
                'result'            => [
                    'route'     => [
                        'name'          => 'grp.org.warehouses.show.inventory.stored_items.current.show',
                        'parameters'    => [
                            'organisation'       => $storedItem->organisation->slug,
                            'warehouse'         => $storedItem->organisation->warehouses->first()->slug, // should has warehouse
                            'storedItem' => $storedItem->slug
                        ]
                    ],
                    'description' => [
                        'label' => $storedItem->fulfilmentCustomer->customer->name
                    ],
                    'code'        => [
                        'label'   => $storedItem->reference,
                        'tooltip' => __('Reference')
                    ],
                    'icon'          => [
                        'icon'  => 'fal fa-user',
                    ],
                    'state_icon' => $storedItem->state->stateIcon()[$storedItem->state->value],
                ]
            ]
        );
    }

}
