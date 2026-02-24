<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 19:26:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Services;

use App\Models\CRM\Customer;
use App\Models\Fulfilment\FulfilmentCustomer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QueryBuilder extends \Spatie\QueryBuilder\QueryBuilder
{
    public function whereElementGroup(
        string $key,
        array $allowedElements,
        callable $engine,
        ?string $prefix = null
    ): self {
        $elementsData = null;

        $argumentName = ($prefix ? $prefix . '_' : '') . 'elements';


        if (request()->has("$argumentName.$key")) {
            $elements               = explode(',', request()->input("$argumentName.$key"));
            $validatedElements      = array_intersect($allowedElements, $elements);
            $countValidatedElements = count($validatedElements);
            if ($countValidatedElements > 0 && $countValidatedElements < count($allowedElements)) {
                $elementsData = $validatedElements;
            }
        }


        if ($elementsData) {
            $engine($this, $elementsData);
        }

        return $this;
    }

    public function whereRadioFilter(
        array $allowedElements,
        string $defaultValue,
        callable $engine,
        ?string $prefix = null
    ): self {
        $elementsData = null;

        $argumentName = ($prefix ? $prefix . '_' : '') . 'radioFilter';
        if (request()->has($argumentName) || $defaultValue) {
            $elements = request()->input("$argumentName") ?? $defaultValue;
            if (is_array($elements)) {
                $elements = Arr::get($elements, 'radio.value');
            }


            $validatedElements      = array_intersect($allowedElements, [$elements]);
            $countValidatedElements = count($validatedElements);
            if ($countValidatedElements > 0 && $countValidatedElements < count($allowedElements)) {
                $elementsData = $elements;
            }
        }

        if ($elementsData) {
            $engine($this, $elementsData);
        }

        return $this;
    }

    public function withFilterPeriod($column, ?string $prefix = null): static
    {
        $table      = $this->getModel()->getTable();
        $periodType = array_key_first(request()->input(($prefix ? $prefix . '_' : '') . 'period') ?? []);

        if ($periodType) {
            $periodData = $this->validatePeriod($periodType, $prefix);

            if ($periodData) {
                $this->whereBetween($table . '.' . $column, [$periodData['start'], $periodData['end']]);
            }
        }

        return $this;
    }


    protected function validatePeriod(string $periodType, ?string $prefix = null): ?array
    {
        $period = request()->input(($prefix ? $prefix . '_' : '') . 'period.' . $periodType);

        return ValidateQueryBuilderPeriods::run($periodType, $period);
    }

    public function withBetweenDates(array $allowedColumns, ?string $prefix = null): static
    {
        $table          = $this->getModel()->getTable();
        $allowedColumns = array_merge($allowedColumns, ['created_at', 'updated_at']);
        $argumentName   = ($prefix ? $prefix . '_' : '') . 'between';

        $filters  = request()->input($argumentName, []);
        $timezone = resolveTimezoneHeader();

        foreach ($allowedColumns as $column) {
            if (array_key_exists($column, $filters)) {
                $range = $filters[$column];
                $parts = explode('-', $range);

                if (count($parts) === 2) {
                    [$start, $end] = $parts;

                    $start = trim($start);
                    $end = trim($end);

                    $start = Carbon::createFromFormat('Ymd', $start, $timezone)
                        ->setTimezone('UTC')
                        ->startOfDay()
                        ->toDateTimeString();

                    $end = Carbon::createFromFormat('Ymd', $end, $timezone)
                        ->setTimezone('UTC')
                        ->endOfDay()
                        ->toDateTimeString();

                    if ($this->getModel() instanceof FulfilmentCustomer) {
                        $this->whereBetween('customers.' . $column, [$start, $end]);
                    } elseif ($this->getModel() instanceof Customer && $column == 'last_invoiced_at') {
                        $this->whereBetween('customer_stats.' . $column, [$start, $end]);
                    } else {
                        $this->whereBetween("$table.$column", [$start, $end]);
                    }
                }
            }
        }

        return $this;
    }

    public function withIrisPaginator(?int $numberOfRecords = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if ($numberOfRecords === null && request()->has('perPage')) {
            $numberOfRecords = (int)request()->input('perPage');
        }

        $perPage = 20;
        if ($numberOfRecords) {
            if ($numberOfRecords > 200) {
                $numberOfRecords = 200;
            } elseif ($numberOfRecords < 10) {
                $numberOfRecords = 10;
            }
            $perPage = $numberOfRecords;
        }

        return $this->paginate(perPage: $perPage);
    }

    public function withRetinaPaginator(?string $prefix, ?int $numberOfRecords = null, $tableName = null, $queryName = 'perPage'): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->processPagination($prefix, $numberOfRecords, $tableName, $queryName, 'web-user');
    }


    public function withPaginator(?string $prefix, ?int $numberOfRecords = null, $tableName = null, $queryName = 'perPage'): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->processPagination($prefix, $numberOfRecords, $tableName, $queryName);
    }


    private function processPagination(?string $prefix, ?int $numberOfRecords = null, $tableName = null, $queryName = 'perPage', $userType = 'user'): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {



        if ($prefix === null) {
            $prefix = '';
        }

        $argumentName = ($prefix ? $prefix . '_' : '') . $queryName;
        if ($numberOfRecords === null && request()->has($argumentName)) {
            $numberOfRecords = (int)request()->input($argumentName);
        }

        $userId      = auth()->user()->id ?? null;
        $keyRppCache = $tableName ? "ui_state-$userType:$userId;rrp-table:" . $prefix . "$tableName" : null;

        if ($numberOfRecords) {
            $perPage = $numberOfRecords;
        } elseif ($tableName) {
            $perPage = Cache::get($keyRppCache) ?? config('ui.table.records_per_page');
        } else {
            $perPage = config('ui.table.records_per_page');
        }


        if ($perPage > config('ui.table.max_records_per_page')) {
            $perPage = config('ui.table.max_records_per_page');
        }

        if ($perPage < config('ui.table.min_records_per_page')) {
            $perPage = config('ui.table.min_records_per_page');
        }


        if ($tableName && $userId) {
            Cache::put($keyRppCache, $perPage, 60 * 60 * 24 * 180); // 6 months in seconds
        }


        return $this->paginate(
            perPage: $perPage,
            pageName: $prefix ? $prefix . 'Page' : 'page'
        );
    }

    /**
     * Add time series aggregation with optional date filtering and last year comparison
     *
     * @param string $timeSeriesTable The time series table name (e.g., 'product_category_time_series')
     * @param string $timeSeriesRecordsTable The time series records table name (e.g., 'product_category_time_series_records')
     * @param string $foreignKey The foreign key to join time series (e.g., 'product_category_id')
     * @param array $aggregateColumns Columns to aggregate with their aliases (e.g., ['sales_grp_currency' => 'sales', 'invoices' => 'invoices'])
     * @param string $frequency The time series frequency enum value (e.g., TimeSeriesFrequencyEnum::DAILY->value)
     * @param string|null $prefix The prefix for request parameters
     * @param bool $includeLY Whether to include last year data (default: true)
     * @param string|null $localKey The local key column to join on (e.g., 'asset_id'). Defaults to 'id'
     *
     * @return array ['hasDateFilter' => bool, 'selectRaw' => array] Array containing filter status and SELECT raw statements
     */
    public function withTimeSeriesAggregation(
        string $timeSeriesTable,
        string $timeSeriesRecordsTable,
        string $foreignKey,
        array $aggregateColumns,
        string $frequency,
        ?string $prefix = null,
        bool $includeLY = true,
        ?string $localKey = null,
        array $additionalFilters = []
    ): array {
        // Parse date filter from request
        $argumentName = ($prefix ? $prefix . '_' : '') . 'between';
        $filters = request()->input($argumentName, []);

        // Fallback to non-prefixed parameter
        if (empty($filters) && $prefix) {
            $filters = request()->input('between', []);
        }

        $hasDateFilter = false;
        $startDate = null;
        $endDate = null;
        $startDateLY = null;
        $endDateLY = null;

        // Check for date filter
        $dateRange = $filters['from'] ?? $filters['date'] ?? null;

        if ($dateRange) {
            $parts = explode('-', $dateRange);

            if (count($parts) === 2) {
                [$start, $end] = $parts;
                $start = trim($start);
                $end = trim($end);

                $timezone = resolveTimezoneHeader();

                $startDate = Carbon::createFromFormat('Ymd', $start, $timezone)
                    ->setTimezone('UTC')
                    ->startOfDay();

                $endDate = Carbon::createFromFormat('Ymd', $end, $timezone)
                    ->setTimezone('UTC')
                    ->endOfDay();

                if ($includeLY) {
                    $startDateLY = $startDate->copy()->subYear();
                    $endDateLY = $endDate->copy()->subYear();
                }

                $hasDateFilter = true;
            }
        }

        // Create a unique alias for the subquery to prevent collisions
        $alias = 'agg_' . substr(md5($timeSeriesTable . $frequency . ($prefix ?? '')), 0, 6);

        // Build the subquery
        $subQuery = DB::table($timeSeriesTable)
            ->join($timeSeriesRecordsTable, "$timeSeriesRecordsTable.{$timeSeriesTable}_id", '=', "$timeSeriesTable.id")
            ->where("$timeSeriesTable.frequency", $frequency)
            ->groupBy("$timeSeriesTable.$foreignKey")
            ->select("$timeSeriesTable.$foreignKey");

        foreach ($additionalFilters as $column => $value) {
            $subQuery->where("{$timeSeriesRecordsTable}.{$column}", $value);
        }

        // Build SELECT raw statements for aggregation (for the subquery)
        // and prepare selectRaw return values (referencing the subquery alias)
        $selectRaw = [];

        foreach ($aggregateColumns as $column => $colAlias) {
            // We use simple aliases inside the subquery to avoid long names, but we need to ensure uniqueness if multiple cols map to similar names?
            // Actually $colAlias is usually 'sales' or 'invoices'. Let's use those.

            // Current period aggregation
            if ($hasDateFilter) {
                $subQuery->selectRaw(
                    "COALESCE(SUM(CASE WHEN {$timeSeriesRecordsTable}.from <= ? " .
                    "AND {$timeSeriesRecordsTable}.to >= ? " .
                    "THEN {$timeSeriesRecordsTable}.{$column} ELSE 0 END), 0) as {$colAlias}",
                    [$endDate->toDateTimeString(), $startDate->toDateTimeString()]
                );
            } else {
                $subQuery->selectRaw("COALESCE(SUM({$timeSeriesRecordsTable}.{$column}), 0) as {$colAlias}");
            }

            // The main query just selects the column from the subquery alias
            $selectRaw[$colAlias] = DB::raw("COALESCE({$alias}.{$colAlias}, 0) as {$colAlias}");

            // Last year aggregation
            if ($includeLY) {
                $aliasLY = $colAlias . '_ly';
                if ($hasDateFilter) {
                    $subQuery->selectRaw(
                        "COALESCE(SUM(CASE WHEN {$timeSeriesRecordsTable}.from <= ? " .
                        "AND {$timeSeriesRecordsTable}.to >= ? " .
                        "THEN {$timeSeriesRecordsTable}.{$column} ELSE 0 END), 0) as {$aliasLY}",
                        [$endDateLY->toDateTimeString(), $startDateLY->toDateTimeString()]
                    );

                    $selectRaw[$aliasLY] = DB::raw("COALESCE({$alias}.{$aliasLY}, 0) as {$aliasLY}");
                } else {
                    // If no date filter, LY is 0 (or undefined logic, currently 0 in original code)
                    $selectRaw[$aliasLY] = DB::raw("0 as {$aliasLY}");
                }
            }
        }

        // Join the subquery to the main query
        $mainTable = $this->getModel()->getTable();
        $joinColumn = $localKey ? "$mainTable.$localKey" : "$mainTable.id";

        $this->leftJoinSub($subQuery, $alias, function ($join) use ($alias, $foreignKey, $joinColumn) {
            $join->on("$alias.$foreignKey", '=', $joinColumn);
        });

        return [
            'hasDateFilter' => $hasDateFilter,
            'selectRaw' => $selectRaw,
        ];
    }
}
