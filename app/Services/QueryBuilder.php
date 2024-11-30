<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 19:26:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Services;

use Illuminate\Support\Carbon;

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

    public function withFilterPeriod($column, ?string $prefix = null): static
    {
        $periodType = array_key_first(request()->input(($prefix ? $prefix . '_' : '') . 'period') ?? []);

        if ($periodType) {
            $periodData = $this->validatePeriod($periodType, $prefix);

            if ($periodData) {
                $this->whereBetween($column, [$periodData['start'], $periodData['end']]);
            }
        }

        return $this;
    }

    protected function validatePeriod(string $periodType, ?string $prefix = null): ?array
    {
        $period = request()->input(($prefix ? $prefix . '_' : '') . 'period.'.$periodType);

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

    public function withPaginator($prefix): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = config('ui.table.records_per_page');

        $argumentName = ($prefix ? $prefix.'_' : '').'perPage';
        if (request()->has($argumentName)) {
            $inputtedPerPage = (int)request()->input($argumentName);

            if ($inputtedPerPage < 10) {
                $perPage = 10;
            } elseif ($inputtedPerPage > 1000) {
                $perPage = 1000;
            } else {
                $perPage = $inputtedPerPage;
            }
        }


        return $this->paginate(
            perPage: $perPage,
            pageName: $prefix ? $prefix.'Page' : 'page'
        );
    }


}
