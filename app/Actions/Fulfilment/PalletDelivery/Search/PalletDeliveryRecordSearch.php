<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:40:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery\Search;

use App\Models\Fulfilment\PalletDelivery;
use Lorisleiva\Actions\Concerns\AsAction;

class PalletDeliveryRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(PalletDelivery $palletDelivery): void
    {

        if ($palletDelivery->trashed()) {

            if ($palletDelivery->universalSearch) {
                $palletDelivery->universalSearch()->delete();
            }
            return;
        }

        $result =  [
            'description'     => [
                'label'   => $palletDelivery->warehouse->name
            ],
            'code'         => [
                'label' => $palletDelivery->reference
            ],
            'icon'          => [
                'icon'  => 'fal fa-truck-couch',
            ],
            'state_icon'          => $palletDelivery->state->stateIcon()[$palletDelivery->state->value],
            'meta'          => [
                [
                    'key'       => __("customer_name"),
                    'label'     => __("Customer") . ': ' . __($palletDelivery->fulfilmentCustomer->customer->name),
                    'tooltip'   => __("Customer")
                ],
                [
                    'type'      => 'number',
                    'afterLabel'     => __('Pallets') . ': ',
                    'number'    => $palletDelivery->stats->number_pallets,
                    'tooltip'   => __("Pallet's count")
                ],
            ],
        ];


        $palletDelivery->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $palletDelivery->group_id,
                'organisation_id'   => $palletDelivery->organisation_id,
                'organisation_slug' => $palletDelivery->organisation->slug,
                'warehouse_id'      => $palletDelivery->warehouse_id,
                'warehouse_slug'    => $palletDelivery->warehouse->slug,
                'fulfilment_id'     => $palletDelivery->fulfilment_id,
                'fulfilment_slug'   => $palletDelivery->fulfilment->slug,
                'sections'          => ['fulfilment'],
                'haystack_tier_1'   => $palletDelivery->reference,
                'result'            => array_merge(
                    [
                        'route'     => [
                            'name'          => 'grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.show',
                            'parameters'    => [
                                'organisation'           => $palletDelivery->organisation->slug,
                                'fulfilment'             => $palletDelivery->fulfilment->slug,
                                'fulfilmentCustomer'     => $palletDelivery->fulfilmentCustomer->slug,
                                'palletDelivery'         => $palletDelivery->slug
                            ]
                        ],
                    ],
                    $result
                ),
                'keyword'           => $palletDelivery->slug
            ]
        );

        $palletDelivery->retinaSearch()->updateOrCreate(
            [],
            [
                'group_id'            => $palletDelivery->group_id,
                'organisation_id'     => $palletDelivery->organisation_id,
                'customer_id'         => $palletDelivery->fulfilmentCustomer->customer_id,
                'haystack_tier_1'     => $palletDelivery->reference,
                'result'              => array_merge([
                    'route'     => [
                        'name'          => 'retina.fulfilment.storage.pallet_deliveries.show',
                        'parameters'    => [
                            'palletDelivery'         => $palletDelivery->slug
                        ]
                    ],
                ], $result),
                'keyword'             => $palletDelivery->slug,
                'keyword_2'           => $palletDelivery->reference
            ]
        );
    }

}
