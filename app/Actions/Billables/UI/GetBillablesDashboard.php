<?php

/*
 * Author: Vika Aqordi
 * Created on 08-01-2026-16h-37m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Billables\UI;

use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsObject;

class GetBillablesDashboard
{
    use AsObject;

    public function handle(Shop $shop): array
    {
        $stats = $shop->stats;

        return [
            'shipping' => [
                'stats' => [
                    'total'           => $stats->number_shipping_zone_schemas,
                    'live'            => $stats->number_shipping_zone_schemas_state_live,
                    'in_process'      => $stats->number_shipping_zone_schemas_state_in_process,
                    'decommissioned'  => $stats->number_shipping_zone_schemas_state_decommissioned,
                ],
                'route' => [
                    'name' => 'grp.org.shops.show.billables.shipping.index',
                    'parameters' => [$shop->organisation->slug, $shop->slug],
                ],
            ],
            'charges' => [
                'stats' => [
                    'total'        => $stats->number_charges,
                    'active'       => $stats->number_charges_state_active,
                    'in_process'   => $stats->number_charges_state_in_process,
                    'discontinued' => $stats->number_charges_state_discontinued,
                ],
                'route' => [
                    'name' => 'grp.org.shops.show.billables.charges.index',
                    'parameters' => [$shop->organisation->slug, $shop->slug],
                ],
            ],
            'services' => [
                'stats' => [
                    'total'        => $stats->number_services,
                    'active'       => $stats->number_services_state_active,
                    'in_process'   => $stats->number_services_state_in_process,
                    'discontinued' => $stats->number_services_state_discontinued,
                ],
                'route' => [
                    'name' => 'grp.org.shops.show.billables.services.index',
                    'parameters' => [$shop->organisation->slug, $shop->slug],
                ],
            ],
        ];
    }
}
