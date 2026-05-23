<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 May 2026 19:15:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI\Traits;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\Goods\TradeUnitsTabsEnum;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait WithTradeUnitStandardIndex
{
    protected function handleStandardTradeUnitIndex(QueryBuilder $queryBuilder, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = $this->tradeUnitGlobalSearch();

        $this->updateQueryBuilderParametersIfPrefixed($prefix);

        $selects = [
            'trade_units.code',
            'trade_units.slug',
            'trade_units.name',
            'trade_units.description',
            'trade_units.gross_weight',
            'trade_units.net_weight',
            'trade_units.marketing_dimensions',
            'trade_units.volume',
            'trade_units.type',
            'trade_unit_stats.number_current_stocks',
            'trade_unit_stats.number_current_products',
            'trade_units.id',
            'trade_units.health_rank',
        ];

        $allowedSorts = ['code', 'type', 'name', 'number_current_stocks', 'number_current_products', 'health_rank'];

        if ($prefix === TradeUnitsTabsEnum::SALES->value) {
            $timeSeriesData = $queryBuilder->withTimeSeriesAggregation(
                timeSeriesTable: 'trade_unit_time_series',
                timeSeriesRecordsTable: 'trade_unit_time_series_records',
                foreignKey: 'trade_unit_id',
                aggregateColumns: [
                    'sales_grp_currency_external' => 'sales_grp_currency_external',
                    'invoices'                    => 'invoices',
                ],
                frequency: TimeSeriesFrequencyEnum::DAILY->value,
                prefix: $prefix
            );

            $selects[] = $timeSeriesData['selectRaw']['sales_grp_currency_external'];
            $selects[] = $timeSeriesData['selectRaw']['sales_grp_currency_external_ly'];
            $selects[] = $timeSeriesData['selectRaw']['invoices'];
            $selects[] = $timeSeriesData['selectRaw']['invoices_ly'];

            $allowedSorts[] = 'sales_grp_currency_external';
            $allowedSorts[] = 'invoices';
        }

        $queryBuilder
            ->defaultSort('trade_units.code')
            ->select($selects);

        return $this->finalizeTradeUnitIndex(
            queryBuilder: $queryBuilder,
            allowedSorts: $allowedSorts,
            globalSearch: $globalSearch,
            prefix: $prefix
        );
    }

    public function standardTradeUnitTableStructure(Group $parent, ?array $modelOperations = null, $prefix = null, bool $sales = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $sales) {
            $emptyState = match (class_basename($parent)) {
                'Group' => [
                    'title' => __("No Trade Units found"),
                ],
                default => null
            };

            $this->setupTradeUnitTable(
                table: $table,
                modelOperations: $modelOperations,
                prefix: $prefix,
                emptyState: $emptyState
            );

            if ($sales) {
                $table->betweenDates(['date']);
                $this->addColumnCodeAndName($table);
                $this->addSalesColumns($table);
                $this->addColumnHealthRank($table);
            } else {
                $this->addColumnCodeAndName($table);
                $this->addColumnNumberCurrentStocks($table);
                $this->addColumnNumberCurrentProducts($table);
                $this->addColumnType($table, 'Unit label');
            }
        };
    }
}
