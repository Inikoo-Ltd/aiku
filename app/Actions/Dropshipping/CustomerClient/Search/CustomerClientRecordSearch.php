<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Sept 2024 11:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerClient\Search;

use App\Models\Dropshipping\CustomerClient;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerClientRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(CustomerClient $customerClient): void
    {

        if ($customerClient->trashed()) {
            $customerClient->universalSearch()->delete();
            return;
        }

        $customerClient->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $customerClient->group_id,
                'organisation_id'   => $customerClient->organisation_id,
                'organisation_slug' => $customerClient->organisation->slug,
                'shop_id'           => $customerClient->shop_id,
                'shop_slug'         => $customerClient->shop->slug,
                'customer_id'       => $customerClient->customer_id,
                'customer_slug'     => $customerClient->customer->slug,
                'sections'          => ['crm'],
                'haystack_tier_1'   => join(
                    ' ',
                    array_unique([
                        $customerClient->name,
                        $customerClient->email,
                        $customerClient->contact_name,
                        $customerClient->company_name
                    ])
                ),
                'result' => [
                    'route'         => [
                        'name'          => 'grp.org.shops.show.crm.customers.show.customer_clients.show',
                        'parameters'    => [
                            $customerClient->organisation->slug,
                            $customerClient->shop->slug,
                            $customerClient->customer->slug,
                            $customerClient->ulid,
                        ]
                    ],
                    'description' => [
                        'label' => $customerClient->shop->name,
                    ],
                    'code'        => [
                        'code' => $customerClient->name
                    ],
                    'icon'      => [
                        'icon' => 'fal fa-user',
                    ],
                    'meta'      => [
                        [
                            'label' => $customerClient->status,
                            'tooltip'   => __('Status'),
                        ],
                        [
                            'type'      => 'address',
                            'label'     => $customerClient->location,
                            'tooltip'   => __('Location')
                        ],
                        [
                            'type'      => 'date',
                            'label'     => $customerClient->created_at,
                            'tooltip'   => __('Since')
                        ],
                    ],
                ]
            ]
        );
    }

}
