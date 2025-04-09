<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:38:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\Search;

use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\Concerns\AsAction;

class PalletReturnRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(PalletReturn $palletReturn): void
    {

        if ($palletReturn->trashed()) {

            if ($palletReturn->universalSearch) {
                $palletReturn->universalSearch()->delete();
            }
            return;
        }

        $result = [
            'description' => [
                'label'   => $palletReturn->warehouse->name
            ],
            'code'         => [
                'label'   => $palletReturn->reference,
                'tooltip' => __('Reference')
            ],
            'icon'          => [
                'icon'  => 'fal fa-truck-couch',
            ],
            'state_icon'         => $palletReturn->state->stateIcon()[$palletReturn->state->value],
            'meta'          => [
                [
                    'key'      => "customer_reference",
                    'label'     =>  $palletReturn->customer_reference,
                    'tooltip'   => __("Return name")
                ],
                [
                    'label'     => $palletReturn->type->labels()[$palletReturn->type->value],
                    'tooltip'   => __("Type")
                ],
                [
                    'type'      => 'number',
                    'label'     => __('Pallets') . ': ',
                    'number'    => $palletReturn->stats->number_pallets,
                    'tooltip'   => __("Pallets")
                ],
            ],
        ];

        $palletReturn->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $palletReturn->group_id,
                'organisation_id'   => $palletReturn->organisation_id,
                'organisation_slug' => $palletReturn->organisation->slug,
                'warehouse_id'      => $palletReturn->warehouse_id,
                'warehouse_slug'    => $palletReturn->warehouse->slug,
                'fulfilment_id'     => $palletReturn->fulfilment_id,
                'fulfilment_slug'   => $palletReturn->fulfilment->slug,
                'sections'          => ['fulfilment'],
                'haystack_tier_1'   => $palletReturn->reference,
                'result'            => array_merge([
                    'route'     => [
                        'name'          => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.show',
                        'parameters'    => [
                            'organisation'           => $palletReturn->organisation->slug,
                            'fulfilment'             => $palletReturn->fulfilment->slug,
                            'fulfilmentCustomer'     => $palletReturn->fulfilmentCustomer->slug,
                            'palletReturn'           => $palletReturn->slug
                        ]
                    ],
                ], $result),
                'keyword'           => $palletReturn->slug
            ]
        );

        $palletReturn->retinaSearch()->updateOrCreate(
            [],
            [
                'group_id'            => $palletReturn->group_id,
                'organisation_id'     => $palletReturn->organisation_id,
                'customer_id'         => $palletReturn->fulfilmentCustomer->customer_id,
                'haystack_tier_1'     => $palletReturn->reference,
                'result'              => array_merge([
                    'route'     => [
                        'name'          => 'retina.fulfilment.storage.pallet_returns.with-stored-items.show',
                        'parameters'    => [
                            'palletReturn'           => $palletReturn->slug
                        ]
                    ],
                ], $result),
                'keyword'             => $palletReturn->slug,
                'keyword_2'           => $palletReturn->reference
            ]
        );
    }

}
