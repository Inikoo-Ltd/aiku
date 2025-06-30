<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 19:26:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Services;

use App\Models\Fulfilment\FulfilmentCustomer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class QueryBuilder extends \Spatie\QueryBuilder\QueryBuilder
{
    public function whereElementGroup(
        string $key,
        array $allowedElements,
        callable $engine,
        ?string $prefix = null
    ): self {
        $elementsData = null;

        $argumentName = ($prefix ? $prefix.'_' : '').'elements';


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

        $argumentName = ($prefix ? $prefix.'_' : '').'radioFilter';
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
        $periodType = array_key_first(request()->input(($prefix ? $prefix.'_' : '').'period') ?? []);

        if ($periodType) {
            $periodData = $this->validatePeriod($periodType, $prefix);

            if ($periodData) {
                $this->whereBetween($table.'.'.$column, [$periodData['start'], $periodData['end']]);
            }
        }

        return $this;
    }


    protected function validatePeriod(string $periodType, ?string $prefix = null): ?array
    {
        $period = request()->input(($prefix ? $prefix.'_' : '').'period.'.$periodType);

        return ValidateQueryBuilderPeriods::run($periodType, $period);
    }

    public function withBetweenDates(array $allowedColumns, ?string $prefix = null): static
    {
        $table          = $this->getModel()->getTable();
        $allowedColumns = array_merge($allowedColumns, ['created_at', 'updated_at']);
        $argumentName   = ($prefix ? $prefix.'_' : '').'between';

        $filters  = request()->input($argumentName, []);
        $timezone = request()->header('X-Timezone');

        foreach ($allowedColumns as $column) {
            if (array_key_exists($column, $filters)) {
                $range = $filters[$column];
                $parts = explode('-', $range);

                if (count($parts) === 2) {
                    [$start, $end] = $parts;

                    $start = trim($start).' 00:00:00';
                    $end   = trim($end).' 23:59:59';

                    $start = Carbon::createFromFormat('Ymd H:i:s', $start, $timezone)
                        ->setTimezone('UTC')
                        ->toDateTimeString();

                    $end = Carbon::createFromFormat('Ymd H:i:s', $end, $timezone)
                        ->setTimezone('UTC')
                        ->toDateTimeString();

                    if ($this->getModel() instanceof FulfilmentCustomer) {
                        $this->whereBetween('customers.'.$column, [$start, $end]);
                    } else {
                        $this->whereBetween("$table.$column", [$start, $end]);
                    }
                }
            }
        }

        return $this;
    }

    public function withIrisPaginator(int $numberOfRecords = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
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



    public function withPaginator(?string $prefix, int $numberOfRecords = null, $tableName = null, $queryName = 'perPage'): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if ($prefix === null) {
            $prefix = '';
        }

        $argumentName = ($prefix ? $prefix.'_' : '').$queryName;
        if ($numberOfRecords === null && request()->has($argumentName)) {
            $numberOfRecords = (int)request()->input($argumentName);
        }

        $userId      = auth()->user()->id ?? null;
        $keyRppCache = $tableName ? "ui_state-user:$userId;rrp-table:".$prefix."$tableName" : null;

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
            pageName: $prefix ? $prefix.'Page' : 'page'
        );
    }

    public function withTrashed()
    {
        $this->queryBuilder->withTrashed();
        return $this;
    }


}
