<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 19 Nov 2025 14:52:52 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalOffersResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $models = $this->resource;

        if (empty($models)) {
            return [
                'slug'    => 'totals',
                'columns' => $this->getEmptyColumns(),
            ];
        }

        $firstModel = is_array($models) ? ($models[0] ?? []) : [];

        $routeTargets = [
           'customers' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.discounts.campaigns.totals.customers',
                    'parameters' => [
                        'organisation'  => $firstModel['organisation_slug'] ?? 'unknown',
                        'shop'          => $firstModel['shop_slug'] ?? 'unknown',
                        'offerCampaign' => $firstModel['slug'] ?? 'unknown'
                    ],
                    'key_date_filter' => 'between[date]',
                ],
           ],
           'orders' => [
                'route_target' => [
                    'name'            => 'grp.org.shops.show.discounts.campaigns.totals.orders',
                    'parameters'      => [
                        'organisation'    => $firstModel['organisation_slug'] ?? 'unknown',
                        'shop'            => $firstModel['shop_slug'] ?? 'unknown',
                        'offerCampaign'   => $firstModel['slug'] ?? 'unknown'
                    ],
                    'key_date_filter' => 'between[date]',
                ]
           ],
           'invoices' => [
                'route_target' => [
                    'name'            => 'grp.org.shops.show.discounts.campaigns.totals.invoices',
                    'parameters'      => [
                        'organisation'    => $firstModel['organisation_slug'] ?? 'unknown',
                        'shop'            => $firstModel['shop_slug'] ?? 'unknown',
                        'offerCampaign'   => $firstModel['slug'] ?? 'unknown'
                    ],
                    'key_date_filter' => 'between[date]',
                ]
           ],
        ];


        $fields = [
            'customers',
            'orders',
            'invoices',
            // 'sales_grp_currency_external',
        ];

        $summedData = $this->sumIntervalValuesFromArrays($models, $fields);

        $summedData = array_merge($firstModel, $summedData);

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => 'All Offer Campaigns',
                    'align'           => 'left',
                ],
                'label_minified' => [
                    'formatted_value' => 'All',
                    'align'           => 'left',
                ],
            ],
            $this->getDashboardColumnsFromArray($summedData, [
                'customers'             => $routeTargets['customers'],
                'customers_minified'    => $routeTargets['customers'],
                'orders'                => $routeTargets['orders'],
                'orders_minified'       => $routeTargets['orders'],
                'orders_delta'          => $routeTargets['orders'],
                'invoices'              => $routeTargets['invoices'],
                'invoices_minified'     => $routeTargets['invoices'],
                'invoices_delta'        => $routeTargets['invoices'],
                // 'sales_grp_currency_external',
                // 'sales_grp_currency_external_minified',
                // 'sales_grp_currency_external_delta',
            ])
        );

        return [
            'slug'    => 'totals',
            'columns' => $columns,
        ];
    }

    private function getEmptyColumns(): array
    {
        return [
            'label' => [
                'formatted_value' => 'Total',
                'align'           => 'left',
            ],
            'label_minified' => [
                'formatted_value' => 'Total',
                'align'           => 'left',
            ],
        ];
    }
}
