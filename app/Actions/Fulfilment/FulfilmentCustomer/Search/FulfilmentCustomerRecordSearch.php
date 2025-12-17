<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:43:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer\Search;

use App\Models\Fulfilment\FulfilmentCustomer;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentCustomerRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(FulfilmentCustomer $fulfilmentCustomer): void
    {

        if ($fulfilmentCustomer->trashed()) {

            if ($fulfilmentCustomer->universalSearch) {
                $fulfilmentCustomer->universalSearch()->delete();
            }
            return;
        }

        $fulfilmentCustomer->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $fulfilmentCustomer->group_id,
                'organisation_id'   => $fulfilmentCustomer->organisation_id,
                'organisation_slug' => $fulfilmentCustomer->organisation->slug,
                'fulfilment_id'     => $fulfilmentCustomer->fulfilment_id,
                'fulfilment_slug'   => $fulfilmentCustomer->fulfilment->slug,
                'sections'          => ['fulfilment'],
                'haystack_tier_1'   => trim($fulfilmentCustomer->customer->email.' '.$fulfilmentCustomer->customer->contact_name.' '.$fulfilmentCustomer->customer->company_name),
                'result'            => [
                    'route'     => [
                        'name'          => 'grp.org.fulfilments.show.crm.customers.show',
                        'parameters'    => [
                            'organisation'       => $fulfilmentCustomer->organisation->slug,
                            'fulfilment'         => $fulfilmentCustomer->fulfilment->slug,
                            'fulfilmentCustomer' => $fulfilmentCustomer->slug
                        ]
                    ],
                    'container'     => [
                        'label' => $fulfilmentCustomer->customer->shop->name
                    ],
                    'description' => [
                        'label' => $fulfilmentCustomer->customer->name
                    ],
                    'code'        => [
                        'label'   => $fulfilmentCustomer->customer->reference,
                        'tooltip' => __('Reference')
                    ],
                    'icon'          => [
                        'icon'  => 'fal fa-user',
                    ],
                    'state_icon' => $fulfilmentCustomer->customer->state->stateIcon()[$fulfilmentCustomer->customer->state->value],
                    'meta'          => [
                        [
                            'key'     => 'contact_name',
                            'label'   => $fulfilmentCustomer->customer->contact_name,
                            'tooltip' => __('Contact name')
                        ],
                        [
                            'key'     => 'email',
                            'label'   => $fulfilmentCustomer->customer->email,
                            'tooltip' => __('Email')
                        ],
                        [
                            'key'     => 'pallet',
                            'label'   => $fulfilmentCustomer->number_pallets,
                            'tooltip' => __('pallet')
                        ],
                    ],
                ]
            ]
        );
    }

}
