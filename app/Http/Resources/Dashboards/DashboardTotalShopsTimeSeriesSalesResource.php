<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalShopsTimeSeriesSalesResource extends JsonResource
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

        $fields = [
            'baskets_created',
            'baskets_created_org_currency',
            'invoices',
            'registrations',
            'sales',
            'sales_org_currency',
        ];

        $summedData = $this->sumIntervalValuesFromArrays($models, $fields);

        $summedData = array_merge($firstModel, $summedData);

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.invoices.index',
                    'parameters' => [
                        'organisation' => $summedData['organisation_slug'] ?? '',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'inBasket' => [
                'route_target' => [
                    'name' => 'grp.org.overview.orders_in_basket.index',
                    'parameters' => [
                        'organisation' => $summedData['organisation_slug'] ?? '',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.org.overview.customers.index',
                    'parameters' => [
                        'organisation' => $summedData['organisation_slug'] ?? '',
                    ],
                    'key_date_filter' => 'between[registered_at]',
                ],
            ],
        ];

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => 'All Shops',
                    'align'           => 'left',
                ],
                'label_minified' => [
                    'formatted_value' => 'All',
                    'tooltip'         => 'All Shops',
                    'align'           => 'left',
                ],
            ],
            $this->getDashboardColumnsFromArray($summedData, [
                'baskets_created' => $routeTargets['inBasket'],
                'baskets_created_minified' => $routeTargets['inBasket'],
                'baskets_created_org_currency' => $routeTargets['inBasket'],
                'baskets_created_org_currency_minified' => $routeTargets['inBasket'],

                'invoices' => $routeTargets['invoices'],
                'invoices_minified' => $routeTargets['invoices'],
                'invoices_delta',

                'registrations' => $routeTargets['registrations'],
                'registrations_minified' => $routeTargets['registrations'],
                'registrations_delta',

                'sales',
                'sales_minified',
                'sales_delta',

                'sales_org_currency',
                'sales_org_currency_minified',
                'sales_org_currency_delta',
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
                'formatted_value' => 'All Shops',
                'align'           => 'left',
            ],
            'label_minified' => [
                'formatted_value' => 'All',
                'tooltip'         => 'All Shops',
                'align'           => 'left',
            ],
        ];
    }
}
