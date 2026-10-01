<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Dashboard;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValuesFromArray;
use Closure;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Appends a Backlog column, in each currency the table shows its sales, to a dashboard sales table whose rows carry GetOrderBacklog values.
 */
class AddDashboardBacklogColumns
{
    use AsObject;
    use WithDashboardIntervalValuesFromArray;

    /**
     * @param array{header: array, body: array, totals: array} $table
     * @param array<int, array> $rows the rows the table body was built from, in the same order
     * @param Closure(array): array|null $rowRouteTarget builds the route a row's Backlog cells link to
     */
    public function handle(array $table, array $rows, bool $inGroup, ?Closure $rowRouteTarget = null): array
    {
        if (!Arr::has($table, ['header.columns', 'body', 'totals'])) {
            return $table;
        }

        $fieldsByCurrencyType = $this->fieldsByCurrencyType($table['header']['columns'], $inGroup);
        if (empty($fieldsByCurrencyType)) {
            return $table;
        }

        $tooltip = __('Orders submitted but not invoiced yet, as of now');
        $keys    = [];
        foreach ($fieldsByCurrencyType as $currencyType => $field) {
            foreach (['full' => $field, 'minified' => $field.'_minified'] as $dataDisplayType => $key) {
                $table['header']['columns'][$key] = [
                    'formatted_value'   => __('Backlog'),
                    'tooltip'           => $tooltip,
                    'currency_type'     => $currencyType,
                    'data_display_type' => $dataDisplayType,
                    'sortable'          => true,
                    'scope'             => $field,
                ];
                $keys[] = $key;
            }
        }

        foreach ($table['body'] as $index => $bodyRow) {
            $row     = $rows[$index] ?? [];
            $columns = $rowRouteTarget ? array_fill_keys($keys, ['route_target' => $rowRouteTarget($row)]) : $keys;

            $table['body'][$index]['columns'] = array_merge($bodyRow['columns'] ?? [], $this->getDashboardColumnsFromArray($row, $columns));
        }

        $table['totals']['columns'] = array_merge($table['totals']['columns'] ?? [], $this->getDashboardColumnsFromArray($this->totalsData($rows, $inGroup), $keys));

        return $table;
    }

    /**
     * @return array<string, string>
     */
    private function fieldsByCurrencyType(array $headerColumns, bool $inGroup): array
    {
        $fields = [];
        foreach ($headerColumns as $key => $column) {
            if (!str_starts_with($key, 'sales') || Arr::get($column, 'data_display_type') !== 'full' || str_contains($key, 'percentage')) {
                continue;
            }

            $currencyType          = Arr::get($column, 'currency_type', 'always');
            $fields[$currencyType] = match ($currencyType) {
                'shop'  => 'backlog',
                'org'   => 'backlog_org_currency_external',
                'grp'   => 'backlog_grp_currency_external',
                default => $inGroup ? 'backlog_grp_currency_external' : 'backlog_org_currency_external',
            };
        }

        return $fields;
    }

    private function totalsData(array $rows, bool $inGroup): array
    {
        $summed    = $this->sumIntervalValuesFromArrays($rows, ['backlog_org_currency_external', 'backlog_grp_currency_external']);
        $firstRow  = $rows[0] ?? [];
        $totalsKey = $inGroup ? 'backlog_grp_currency_external' : 'backlog_org_currency_external';
        $currency  = $inGroup ? ($firstRow['group_currency_code'] ?? 'GBP') : ($firstRow['organisation_currency_code'] ?? 'GBP');

        $data = [
            'shop_currency_code'         => $currency,
            'organisation_currency_code' => $currency,
            'group_currency_code'        => $firstRow['group_currency_code'] ?? 'GBP',
        ];

        foreach ($summed as $key => $value) {
            $data[$key] = $value;
            if (str_starts_with($key, $totalsKey)) {
                $interval                                  = substr($key, strlen($totalsKey));
                $data['backlog'.$interval]                 = $value;
                $data['backlog_org_currency_external'.$interval] = $value;
            }
        }

        return $data;
    }
}
