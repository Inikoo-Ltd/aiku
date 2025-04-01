<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 19:26:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Services;

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
            if ($countValidatedElements > 0 and $countValidatedElements < count($allowedElements)) {
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
        if (request()->has($argumentName) or $defaultValue) {
            $elements = request()->input("$argumentName") ?? $defaultValue;
            if (is_array($elements)) {
                $elements = Arr::get($elements, 'radio.value');
            }


            $validatedElements      = array_intersect($allowedElements, [$elements]);
            $countValidatedElements = count($validatedElements);
            if ($countValidatedElements > 0 and $countValidatedElements < count($allowedElements)) {
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

        $start = null;
        $end   = null;

        switch ($periodType) {
            case 'day':
                if ($period && preg_match('/^\d{8}$/', $period)) {
                    $start = Carbon::createFromFormat('Ymd', $period)->startOfDay()->toDateTimeString();
                    $end   = Carbon::createFromFormat('Ymd', $period)->endOfDay()->toDateTimeString();
                } else {
                    return null;
                }
                break;
            case 'yesterday':
                $start = now()->subDay()->startOfDay()->toDateTimeString();
                $end   = now()->subDay()->endOfDay()->toDateTimeString();
                break;
            case 'week':
                if ($period && preg_match('/^\d{4}\d{2}$/', $period)) {
                    $year = substr($period, 0, 4);
                    $week = substr($period, 4, 2);
                    $date = Carbon::now()->setISODate($year, $week);

                    $start = $date->startOfWeek()->toDateTimeString();
                    $end   = $date->endOfWeek()->toDateTimeString();
                } else {
                    return null;
                }
                break;
            case 'month':
                if (preg_match('/^\d{4}\d{2}$/', $period)) {
                    $start = Carbon::createFromFormat('Ym', $period)->startOfMonth()->toDateTimeString();
                    $end   = Carbon::createFromFormat('Ym', $period)->endOfMonth()->toDateTimeString();
                } else {
                    return null;
                }
                break;
            case 'year':
                if (preg_match('/^\d{4}$/', $period)) {
                    $start = Carbon::createFromFormat('Y', $period)->startOfYear()->toDateTimeString();
                    $end   = Carbon::createFromFormat('Y', $period)->endOfYear()->toDateTimeString();
                } else {
                    return null;
                }
                break;
            case 'quarter':
                if ($period && preg_match('/^\d{4}Q[1-4]$/', $period)) {
                    $year    = substr($period, 0, 4);
                    $quarter = substr($period, 5, 1);

                    switch ($quarter) {
                        case '1':
                            $start = Carbon::create($year)->startOfQuarter()->toDateTimeString();
                            $end   = Carbon::create($year)->endOfQuarter()->toDateTimeString();
                            break;
                        case '2':
                            $start = Carbon::create($year, 4)->startOfQuarter()->toDateTimeString();
                            $end   = Carbon::create($year, 4)->endOfQuarter()->toDateTimeString();
                            break;
                        case '3':
                            $start = Carbon::create($year, 7)->startOfQuarter()->toDateTimeString();
                            $end   = Carbon::create($year, 7)->endOfQuarter()->toDateTimeString();
                            break;
                        case '4':
                            $start = Carbon::create($year, 10)->startOfQuarter()->toDateTimeString();
                            $end   = Carbon::create($year, 10)->endOfQuarter()->toDateTimeString();
                            break;
                    }
                } else {
                    return null;
                }
                break;
            default:
                return null;
        }

        return ['start' => $start, 'end' => $end];
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

                    $this->whereBetween("$table.$column", [$start, $end]);
                }
            }
        }

        return $this;
    }


    public function withPaginator($prefix, int $numberOfRecords = null, $tableName = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $argumentName = ($prefix ? $prefix.'_' : '').'perPage';
        if ($numberOfRecords === null and request()->has($argumentName)) {
            $numberOfRecords = (int)request()->input($argumentName);
        }

        $userId      = auth()->user()->id ?? null;
        $keyRppCache = $tableName ? "ui_state-user:$userId;rrp-table:".($prefix ? "$prefix." : "")."$tableName" : null;

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


}
