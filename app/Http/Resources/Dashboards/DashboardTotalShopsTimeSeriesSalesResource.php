<?php

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use App\Enums\Dashboards\GroupDashboardSalesTableTabsEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTotalShopsTimeSeriesSalesResource extends JsonResource
{
    use WithDashboardIntervalValuesFromArray;

    protected ?GroupDashboardSalesTableTabsEnum $context = null;

    public function withContext(?GroupDashboardSalesTableTabsEnum $context): self
    {
        $this->context = $context;
        return $this;
    }

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
        $parentType = $firstModel['parent_type'] ?? 'Organisation';

        $fields = [
            'baskets_created',
            'baskets_created_org_currency',
            'baskets_created_grp_currency',
            'invoices',
            'registrations',
            'registrations_with_orders',
            'registrations_without_orders',
            'sales',
            'sales_org_currency',
            'sales_grp_currency',
        ];

        $summedData = $this->sumIntervalValuesFromArrays($models, $fields);

        $summedData = array_merge([
            'organisation_slug' => $firstModel['organisation_slug'] ?? 'unknown',
            'shop_currency_code' => $firstModel['shop_currency_code'] ?? 'GBP',
            'organisation_currency_code' => $firstModel['organisation_currency_code'] ?? 'GBP',
            'group_currency_code' => $firstModel['group_currency_code'] ?? 'GBP',
            'parent_type' => $parentType,
        ], $summedData);

        $routeTargets = [
            'invoices' => [
                'route_target' => [
                    'name' => 'grp.org.accounting.invoices.index',
                    'parameters' => [
                        'organisation' => $summedData['organisation_slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'inBasket' => [
                'route_target' => [
                    'name' => 'grp.org.overview.orders_in_basket.index',
                    'parameters' => [
                        'organisation' => $summedData['organisation_slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[date]',
                ],
            ],
            'registrations' => [
                'route_target' => [
                    'name' => 'grp.org.overview.customers.index',
                    'parameters' => [
                        'organisation' => $summedData['organisation_slug'] ?? 'unknown',
                    ],
                    'key_date_filter' => 'between[registered_at]',
                ],
            ],
        ];

        $columnsConfig = [];

        if ($parentType === 'Organisation') {
            $columnsConfig = [
                'baskets_created' => $routeTargets['inBasket'],
                'baskets_created_minified' => $routeTargets['inBasket'],
                'baskets_created_org_currency' => $routeTargets['inBasket'],
                'baskets_created_org_currency_minified' => $routeTargets['inBasket'],

                'registrations_delta',

                'invoices' => $routeTargets['invoices'],
                'invoices_minified' => $routeTargets['invoices'],
                'invoices_delta',

                'sales',
                'sales_minified',
                'sales_delta',

                'sales_org_currency',
                'sales_org_currency_minified',
                'sales_org_currency_delta',
            ];
        } else {
            $columnsConfig = [
                'baskets_created_org_currency' => $routeTargets['inBasket'],
                'baskets_created_org_currency_minified' => $routeTargets['inBasket'],

                'baskets_created_grp_currency' => $routeTargets['inBasket'],
                'baskets_created_grp_currency_minified' => $routeTargets['inBasket'],

                'registrations_delta',

                'invoices' => $routeTargets['invoices'],
                'invoices_minified' => $routeTargets['invoices'],
                'invoices_delta',

                'sales_org_currency',
                'sales_org_currency_minified',
                'sales_org_currency_delta',

                'sales_grp_currency',
                'sales_grp_currency_minified',
                'sales_grp_currency_delta',
            ];
        }

        $labelFull = match ($this->context) {
            GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => 'All Dropshipping',
            GroupDashboardSalesTableTabsEnum::GLOBAL_FULFILMENT => 'All Fulfilment',
            default => 'All Shops',
        };

        $labelTooltip = match ($this->context) {
            GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => 'All Dropshipping',
            GroupDashboardSalesTableTabsEnum::GLOBAL_FULFILMENT => 'All Fulfilment',
            default => 'All Shops',
        };

        $registrationsColumns = $this->getDashboardColumnsFromArray($summedData, [
            'registrations' => $routeTargets['registrations'],
            'registrations_minified' => $routeTargets['registrations'],
        ]);

        $registrationsColumns = $this->addRegistrationsTooltip($registrationsColumns, $summedData);

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $labelFull,
                    'align'           => 'left',
                ],
                'label_minified' => [
                    'formatted_value' => 'All',
                    'tooltip'         => $labelTooltip,
                    'align'           => 'left',
                ],
            ],
            $this->getDashboardColumnsFromArray($summedData, $columnsConfig),
            $registrationsColumns
        );

        if ($parentType === 'Organisation') {
            $columns['sales'] = $columns['sales_org_currency'];
            $columns['sales_minified'] = $columns['sales_org_currency_minified'];
            $columns['sales_delta'] = $columns['sales_org_currency_delta'];
            $columns['baskets_created'] = $columns['baskets_created_org_currency'];
            $columns['baskets_created_minified'] = $columns['baskets_created_org_currency_minified'];
        } else {
            $columns['sales_org_currency'] = $columns['sales_grp_currency'];
            $columns['sales_org_currency_minified'] = $columns['sales_grp_currency_minified'];
            $columns['sales_org_currency_delta'] = $columns['sales_grp_currency_delta'];
            $columns['baskets_created_org_currency'] = $columns['baskets_created_grp_currency'];
            $columns['baskets_created_org_currency_minified'] = $columns['baskets_created_grp_currency_minified'];
        }

        return [
            'slug'    => 'totals',
            'columns' => $columns,
        ];
    }

    private function getEmptyColumns(): array
    {
        $labelFull = match ($this->context) {
            GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => 'All Dropshipping',
            GroupDashboardSalesTableTabsEnum::GLOBAL_FULFILMENT => 'All Fulfilment',
            default => 'All Shops',
        };

        $labelTooltip = match ($this->context) {
            GroupDashboardSalesTableTabsEnum::GLOBAL_DROPSHIPPING => 'All Dropshipping',
            GroupDashboardSalesTableTabsEnum::GLOBAL_FULFILMENT => 'All Fulfilment',
            default => 'All Shops',
        };

        return [
            'label' => [
                'formatted_value' => $labelFull,
                'align'           => 'left',
            ],
            'label_minified' => [
                'formatted_value' => 'All',
                'tooltip'         => $labelTooltip,
                'align'           => 'left',
            ],
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
                            'With orders: %s | Without orders: %s',
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
