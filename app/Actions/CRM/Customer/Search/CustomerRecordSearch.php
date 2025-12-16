<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:13:05 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Search;

use App\Models\CRM\Customer;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Customer $customer): void
    {
        if ($customer->trashed()) {
            $customer->universalSearch()->delete();

            return;
        }

        $modelData =  [
            'sections' => ['crm'],
            'group_id'          => $customer->group_id,
            'organisation_id'   => $customer->organisation_id,
            'organisation_slug' => $customer->organisation->slug,
            'customer_id'       => $customer->id,
            'customer_slug'     => $customer->slug,
            'haystack_tier_1'   => trim($customer->email.' '.$customer->contact_name.' '.$customer->company_name),
            'haystack_tier_2'   => trim($customer->internal_notes.' '.$customer->warehouse_internal_notes.' '.$customer->warehouse_public_notes),

            'result' => [
                'icon'        => [
                    'icon' => 'fal fa-user',
                    'model' => 'customer'
                ],
                'code'        => [
                    'label'   => $customer->reference,
                    'tooltip' => __('Reference')
                ],
                'description' => [
                    'label' => $customer->name
                ],
                'container'   => [
                    'label' => $customer->shop->name,
                ],

                'state_icon' => $customer->state->stateIcon()[$customer->state->value],

                'meta' => [
                    [
                        'key'     => 'created_date',
                        'type'    => 'date',
                        'label'   => $customer->created_at,
                        'tooltip' => __('Created at')
                    ],
                    [
                        'key'     => 'address',
                        'type'    => 'address',
                        'label'   => $customer->location,
                        'tooltip' => __('Location')
                    ],
                    [
                        'key'     => 'contact_name',
                        'label'   => $customer->contact_name,
                        'tooltip' => __('Contact name')
                    ],
                    [
                        'key'     => 'email',
                        'label'   => $customer->email,
                        'tooltip' => __('Email')
                    ],

                    [
                        'key'     => 'phone',
                        'label'   => $customer->phone,
                        'tooltip' => __('Phone')
                    ],

                ],
            ]
        ];

        if ($customer->is_fulfilment && $customer->shop->exists) {
            $modelData['fulfilment_id'] = $customer->shop->fulfilment_id;
            $modelData['fulfilment_slug'] = $customer->shop->fulfilment->slug;
            $modelData['result'] = array_merge_recursive($modelData['result'], [
                'route' => [
                    'name'          => 'grp.org.fulfilments.show.crm.customers.show',
                    'parameters'    => [
                        $customer->organisation->slug,
                        $customer->shop->slug,
                        $customer->slug
                    ]
                ]
            ]);
        } else {
            $modelData['shop_id'] = $customer->shop_id;
            $modelData['shop_slug'] = $customer->shop->slug;
            $modelData['result'] = array_merge_recursive($modelData['result'], [
                'route' => [
                    'name'          => 'grp.org.shops.show.crm.customers.show',
                    'parameters'    => [
                        $customer->organisation->slug,
                        $customer->shop->slug,
                        $customer->slug
                    ]
                ]
            ]);
        }



        $customer->universalSearch()->updateOrCreate(
            [

            ],
            $modelData
        );
    }

}
