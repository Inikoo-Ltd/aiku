<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 20:58:56 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Rental\Search;

use App\Models\Billables\Rental;
use Lorisleiva\Actions\Concerns\AsAction;

class RentalRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Rental $rental): void
    {

        if ($rental->trashed()) {

            if ($rental->universalSearch) {
                $rental->universalSearch()->delete();
            }
            return;
        }

        $rental->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $rental->group_id,
                'organisation_id'   => $rental->organisation_id,
                'organisation_slug' => $rental->organisation->slug,
                'shop_id'           => $rental->shop_id,
                'shop_slug'         => $rental->shop->slug,
                'fulfilment_id'     => $rental->shop->fulfilment->id,
                'fulfilment_slug'   => $rental->shop->fulfilment->slug,
                'sections'          => ['fulfilment'],
                'haystack_tier_1'   => $rental->name,
                'result' => [
                    'route' => [
                        'name'       => 'grp.org.fulfilments.show.catalogue.rentals.show',
                        'parameters' => [
                            'organisation' => $rental->organisation->slug,
                            'fulfilment'   => $rental->shop->fulfilment->slug,
                            'rental'       => $rental->slug
                        ]
                    ],
                    'icon'        => [
                        'icon' => 'fal fa-garage',
                    ],
                    'code'        => [
                        'label'   => $rental->code,
                        'tooltip' => __('Code')
                    ],
                    'description' => [
                        'label' => $rental->name
                    ],
                    'container'   => [
                        'label' => $rental->shop->name,
                    ],

                    'state_icon' => $rental->state->stateIcon()[$rental->state->value],

                    'meta' => [
                        [
                            'key'       => 'price',
                            'type'      => 'currency',
                            'code'      => $rental->currency->code,
                            'label'     => __('Price') . ': ',
                            'amount'    => $rental->price,
                            'tooltip'   => __('Price')
                        ],
                        ($rental->auto_assign_asset || $rental->auto_assign_asset_type) ?
                        [
                            'key'     => 'workflow',
                            'label'   =>    __($rental->auto_assign_asset) . ': ' . __($rental->auto_assign_asset_type),
                            'tooltip' => __('Workflow')
                        ] : [],
                    ],
                ]
            ]
        );
    }

}
