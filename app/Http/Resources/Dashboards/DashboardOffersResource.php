<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 19 Nov 2025 12:48:35 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
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
               ],
           ],
           'customers' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.discounts.campaigns.customers',
                    'parameters' => [
                        'organisation'  => $data['organisation_slug'] ?? 'unknown',
                        'shop'          => $data['shop_slug'] ?? 'unknown',
                        'offerCampaign' => $data['slug'] ?? 'unknown'
                    ],
                    'key_date_filter' => 'between[date]',
                ],
           ],
           'orders' => [
                'route_target' => [
                    'name'            => 'grp.org.shops.show.discounts.campaigns.orders',
                    'parameters'      => [
                        'organisation'    => $data['organisation_slug'] ?? 'unknown',
                        'shop'            => $data['shop_slug'] ?? 'unknown',
                        'offerCampaign'   => $data['slug'] ?? 'unknown'
                    ],
                    'key_date_filter' => 'between[date]',
                ]
           ],
           'invoices' => [
                'route_target' => [
                    'name'            => 'grp.org.shops.show.discounts.campaigns.invoices',
                    'parameters'      => [
                        'organisation'    => $data['organisation_slug'] ?? 'unknown',
                        'shop'            => $data['shop_slug'] ?? 'unknown',
                        'offerCampaign'   => $data['slug'] ?? 'unknown'
                    ],
                    'key_date_filter' => 'between[date]',
                ]
           ],
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => '['.($data['code'] ?? 'Unknown').'] '.($data['name'] ?? 'Unknown'),
                    'align'           => 'left',
                    'icon_left'       => OfferCampaignTypeEnum::from($data['type'])->icons()[$data['type']],
                    ...$routeTargets['label']
                ],
                'label_minified' => [
                    'formatted_value' => $data['code'] ?? 'Unknown',
                    'align'           => 'left',
                    'icon_left'       => OfferCampaignTypeEnum::from($data['type'])->icons()[$data['type']],
                    ...$routeTargets['label']
                ]
            ],
            $this->getDashboardColumnsFromArray($data, [
                'customers'             => $routeTargets['customers'],
                'customers_minified'    => $routeTargets['customers'],
                'orders'                => $routeTargets['orders'],
                'orders_minified'       => $routeTargets['orders'],
                'orders_delta'          => $routeTargets['orders'],
                'invoices'              => $routeTargets['invoices'],
                'invoices_minified'     => $routeTargets['invoices'],
                'invoices_delta'        => $routeTargets['invoices'],
                'sales_grp_currency_external',
                'sales_grp_currency_external_minified',
                'sales_grp_currency_external_delta'
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
