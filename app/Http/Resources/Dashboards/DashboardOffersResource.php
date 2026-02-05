<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 19 Nov 2025 12:48:35 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardOffersResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $data = (array) $this->resource;

        $routeTargets = [
           'label' => [
               'route_target' => [
                   'name' => 'grp.org.shops.show.discounts.campaigns.show',
                   'parameters' => [
                       'organisation'  => $data['organisation_slug'] ?? 'unknown',
                       'shop'          => $data['shop_slug'] ?? 'unknown',
                       'offerCampaign' => $data['slug'] ?? 'unknown'
                   ]
               ]
           ]
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $data['name'] ?? 'Unknown',
                    'align'           => 'left',
                    ...$routeTargets['label']
                ],
                'label_minified' => [
                    'formatted_value' => $data['code'] ?? 'Unknown',
                    'align'           => 'left',
                    ...$routeTargets['label']
                ]
            ],
            $this->getDashboardColumnsFromArray($data, [
                'customers',
                'customers_minified',
                'orders',
                'orders_minified',
                'orders_delta',
                'invoices',
                'invoices_minified',
                'invoices_delta',
                'sales',
                'sales_minified',
                'sales_delta'
            ])
        );

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => $data['state'] ?? 'active',
            'columns' => $columns,
            'colour'  => ''
        ];
    }
}
