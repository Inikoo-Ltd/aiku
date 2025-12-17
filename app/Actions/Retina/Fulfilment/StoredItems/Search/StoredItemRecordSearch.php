<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 07-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Fulfilment\StoredItems\Search;

use App\Models\Fulfilment\StoredItem;
use Lorisleiva\Actions\Concerns\AsAction;

class StoredItemRecordSearch
{
    use AsAction;

    public string $jobQueue = 'retina-search';

    public function handle(StoredItem $storedItem): void
    {
        $storedItem->retinaSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $storedItem->group_id,
                'organisation_id'   => $storedItem->organisation_id,
                'haystack_tier_1'   => trim($storedItem->reference . ' ' .$storedItem->fulfilmentCustomer->customer->name),
                'haystack_tier_2'   => $storedItem->notes,
                'keyword'           => $storedItem->slug,
                'keyword_2'         => trim($storedItem->reference),
                'result'            => [
                    'route'     => [
                        'name'          => 'retina.fulfilment.storage.stored-items.show',
                        'parameters'    => [
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
