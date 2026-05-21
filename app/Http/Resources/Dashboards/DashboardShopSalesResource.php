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
            'shops' => [
                'route_target' => isset($data['id']) ? [
                    'name' => 'grp.helpers.redirect_shops_from_dashboard',
                    'parameters' => [
                        'shop' => $data['id'],
                    ],
                ] : null,
            ],
        ];

        $migrationTooltip = 'Migrated to Aiku';
        if (!empty($data['migrated_to_aiku_on'])) {
            $migrationTooltip .= ' on ' . $data['migrated_to_aiku_on'];
        }

        $migrationIcon = ($data['is_aiku'] ?? false) ? [
            'icon_right' => [
                'img'   => '/favicon.svg' ?? null,
                'tooltip' => $migrationTooltip,
            ],
        ] : [];

        $columns = [
            'link' => [
                'clickable_icon' => $data['website_url'] ? [
                    'url'  => $data['website_url'],
                    'icon' => ['icon' => 'fal fa-external-link'],
                ] : null,
            ],
            'label' => [
                'formatted_value' => $data['name'] ?? 'Unknown',
                'align'           => 'left',
                ...$routeTargets['shops'],
                ...$migrationIcon,
            ],
            'label_minified' => [
                'formatted_value' => Abbreviate::run($data['name'] ?? 'Unknown'),
                'tooltip'         => $data['name'] ?? 'Unknown',
                'align'           => 'left',
                ...$routeTargets['shops'],
                ...$migrationIcon,
            ]
        ];

        $registrationsColumns = $this->getDashboardColumnsFromArray($data, [
            'registrations' => $routeTargets['registrations'],
            'registrations_minified' => $routeTargets['registrations'],
        ]);

        $registrationsColumns = $this->addRegistrationsTooltip($registrationsColumns, $data);

        $columns = array_merge(
            $columns,
            $this->getDashboardColumnsFromArray($data, [
                'invoices' => $routeTargets['invoices'],
                'invoices_minified' => $routeTargets['invoices'],
                'invoices_delta',
            ]),
            $registrationsColumns,
            $this->getDashboardColumnsFromArray($data, [
                'registrations_delta',
                'sales_external',
                'sales_external_minified',
                'sales_external_delta',
                'sales_org_currency_external',
                'sales_org_currency_external_minified',
                'sales_org_currency_external_delta',
                'sales_grp_currency_external',
                'sales_grp_currency_external_minified',
                'sales_grp_currency_external_delta',
            ])
        );

        return [
            'slug'    => $data['slug'] ?? 'unknown',
            'state'   => ($data['state'] ?? ShopStateEnum::OPEN->value) == ShopStateEnum::OPEN->value ? 'active' : 'inactive',
            'columns' => $columns,
            'colour'  => $data['colour'] ?? '',
        ];
    }

    private function addRegistrationsTooltip(array $columns, array $data): array
    {
        $intervals = ['tdy', 'ld', '3d', '1w', '1m', '1q', '1y', 'all', 'ytd', 'qtd', 'mtd', 'wtd', 'lm', 'lw', 'ctm'];

        foreach (['registrations', 'registrations_minified'] as $columnKey) {
            if (isset($columns[$columnKey])) {
                foreach ($intervals as $interval) {
                    if (isset($columns[$columnKey][$interval])) {
                        $withOrders = $data["registrations_with_orders_{$interval}"] ?? 0;
                        $withoutOrders = $data["registrations_without_orders_{$interval}"] ?? 0;

                        $columns[$columnKey][$interval]['tooltip'] = sprintf(
                            'With product in basket: %s | With empty basket: %s',
                            number_format($withOrders),
                            number_format($withoutOrders)
                        );
                    }
                }
            }
        }

        return $columns;
    }
}
