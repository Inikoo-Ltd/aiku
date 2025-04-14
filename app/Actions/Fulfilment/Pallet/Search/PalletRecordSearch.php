<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:39:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet\Search;

use App\Models\Billables\Rental;
use App\Models\Fulfilment\Pallet;
use Lorisleiva\Actions\Concerns\AsAction;

class PalletRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Pallet $pallet): void
    {
        if ($pallet->trashed()) {

            if ($pallet->universalSearch) {
                $pallet->universalSearch()->delete();
            }
            return;
        }

        $rental = Rental::find($pallet->rental_id) ?? null;

        $result = [
            'icon'        => [
                'icon' => 'fal fa-pallet',
            ],
            'code'        => [
                'label'   => $pallet->reference,
                'tooltip' => __('Reference')
            ],
            'description' => [
                'label' => $rental->name ?? ''
            ],
            'state_icon'          => $pallet->type->typeIcon()[$pallet->type->value],
            'meta'          => [
                [
                    'key'       => __("customer_reference"),
                    'label'     => __("Pallet reference (customer's), notes") . ': ' . __($pallet->customer_reference),
                    'tooltip'   => __("Pallet reference (customer's), notes")
                ],
                [
                    'key'       => __("state"),
                    'icon'      => $pallet->state->stateIcon()[$pallet->state->value],
                    'label'     => __("State") . ': ' . __($pallet->state->value),
                    'tooltip'   => __("State")
                ],
                [
                    'key'       => __("status"),
                    'icon'      => $pallet->status->statusIcon()[$pallet->status->value],
                    'label'     => __("Status") . ': ' . __($pallet->status->value),
                    'tooltip'   => __("Status")
                ],
            ],
        ];

        $pallet->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'            => $pallet->group_id,
                'organisation_id'     => $pallet->organisation_id,
                'organisation_slug'   => $pallet->organisation->slug,
                'fulfilment_id'       => $pallet->fulfilment_id,
                'fulfilment_slug'     => $pallet->fulfilment->slug,
                'warehouse_id'        => $pallet->warehouse_id,
                'warehouse_slug'      => $pallet->warehouse->slug,
                'sections'            => ['fulfilment'],
                'haystack_tier_1'     => trim($pallet->reference . ' ' . ($rental->name ?? '') . ' ' . $pallet->customer_reference),
                'keyword'             => $pallet->slug,
                'keyword_2'           => $pallet->reference,
                'result'              => array_merge([
                    'route'     => [
                        'name'          => 'grp.org.fulfilments.show.crm.customers.show.pallets.show',
                        'parameters'    => [
                            'organisation'       => $pallet->organisation->slug,
                            'fulfilment'         => $pallet->fulfilment->slug,
                            'fulfilmentCustomer' => $pallet->fulfilmentCustomer->slug,
                            'pallet'             => $pallet->slug
                        ]
                    ],
                ], $result),
            ]
        );

        $pallet->retinaSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $pallet->group_id,
                'organisation_id'   => $pallet->organisation_id,
                'customer_id'       => $pallet->fulfilmentCustomer->customer_id,
                'haystack_tier_1'   => $pallet->reference ?? $pallet->id,
                'result'              => array_merge([
                    'route'     => [
                        'name'          => 'retina.fulfilment.storage.pallets.show',
                        'parameters'    => [
                            'pallet'             => $pallet->slug
                        ]
                    ],
                ], $result),
            ]
        );

    }

}
