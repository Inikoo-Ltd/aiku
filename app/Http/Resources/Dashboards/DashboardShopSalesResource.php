<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use App\Actions\Utils\Abbreviate;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardShopSalesResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    public function toArray($request): array
    {
        $data = (array) $this->resource;

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.dashboard.invoices.index',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'] ?? 'unknown',
                        'shop' => $data['slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.crm.customers.index',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'] ?? 'unknown',
                        'shop' => $data['slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[registered_at]',
                ],
            ],
            'inBasket' => [
                'route_target' => [
                    'name' => 'grp.org.shops.show.ordering.backlog',
                    'parameters' => [
                        'organisation' => $data['organisation_slug'] ?? 'unknown',
                        'shop' => $data['slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'shops' => [
                'route_target' => isset($data['id']) ? [
                    'name' => 'grp.helpers.redirect_shops_from_dashboard',
                    'parameters' => [
                        'shop' => $data['id'],
                    ],
                ] : null,
            ],
        ];

        $columns = [
            'label' => [
                'formatted_value' => $data['name'] ?? 'Unknown',
                'align'           => 'left',
                ...$routeTargets['shops']
            ],
            'label_minified' => [
                'formatted_value' => Abbreviate::run($data['name'] ?? 'Unknown'),
                'tooltip'         => $data['name'] ?? 'Unknown',
                'align'           => 'left',
                ...$routeTargets['shops']
            ]
        ];

        $columns = array_merge(
            $columns,
            $this->getDashboardColumnsFromArray($data, [
                'baskets_created' => $routeTargets['inBasket'],
                'baskets_created_minified' => $routeTargets['inBasket'],
                'baskets_created_org_currency' => $routeTargets['inBasket'],
                'baskets_created_org_currency_minified' => $routeTargets['inBasket'],
                'baskets_created_grp_currency' => $routeTargets['inBasket'],
                'baskets_created_grp_currency_minified' => $routeTargets['inBasket'],
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
                'sales_grp_currency',
                'sales_grp_currency_minified',
                'sales_grp_currency_delta',
            ])
        );

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => ($data['state'] ?? ShopStateEnum::OPEN->value) == ShopStateEnum::OPEN->value ? 'active' : 'inactive',
            'columns' => $columns,
            'colour'  => $data['colour'] ?? '',
        ];
    }
}
