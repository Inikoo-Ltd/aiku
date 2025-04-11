<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Apr 2025 12:56:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Services;

use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

class ValidateQueryBuilderPeriods
{
    use asObject;

    public function handle(string $periodType, string $period): ?array
    {
        return match ($periodType) {
            'day' => $this->validateDay($period),
            'yesterday' => $this->validateYesterday(),
            'week' => $this->validateWeek($period),
            'month' => $this->validateMonth($period),
            'year' => $this->validateYear($period),
            'quarter' => $this->validateQuarter($period),
            default => null
        };
    }

    private function validateQuarter(string $period): ?array
    {
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
                default:
                    return null;
            }

            return [
                'start' => $start,
                'end'   => $end
            ];
        } else {
            return null;
        }
    }

    private function validateYear(string $period): ?array
    {
        if (preg_match('/^\d{4}$/', $period)) {
            $start = Carbon::createFromFormat('Y', $period)->startOfYear()->toDateTimeString();
            $end   = Carbon::createFromFormat('Y', $period)->endOfYear()->toDateTimeString();

            return [
                'start' => $start,
                'end'   => $end
            ];
        } else {
            return null;
        }
    }

    private function validateMonth(string $period): ?array
    {
        if (preg_match('/^\d{4}\d{2}$/', $period)) {
            $start = Carbon::createFromFormat('Ym', $period)->startOfMonth()->toDateTimeString();
            $end   = Carbon::createFromFormat('Ym', $period)->endOfMonth()->toDateTimeString();

            return [
                'start' => $start,
                'end'   => $end
            ];
        } else {
            return null;
        }
    }

    private function validateWeek(string $period): ?array
    {
        if ($period && preg_match('/^\d{4}\d{2}$/', $period)) {
            $year = substr($period, 0, 4);
            $week = substr($period, 4, 2);
            $date = Carbon::now()->setISODate($year, $week);

            $start = $date->startOfWeek()->toDateTimeString();
            $end   = $date->endOfWeek()->toDateTimeString();

            return [
                'start' => $start,
                'end'   => $end
            ];
        } else {
            return null;
        }
    }


    private function validateDay(string $period): ?array
    {
        if ($period && preg_match('/^\d{8}$/', $period)) {
            $start = Carbon::createFromFormat('Ymd', $period)->startOfDay()->toDateTimeString();
            $end   = Carbon::createFromFormat('Ymd', $period)->endOfDay()->toDateTimeString();

            return [
                'start' => $start,
                'end'   => $end
            ];
        } else {
            return null;
        }
    }

    private function validateYesterday(): ?array
    {
        $start = now()->subDay()->startOfDay()->toDateTimeString();
        $end   = now()->subDay()->endOfDay()->toDateTimeString();

        return [
            'start' => $start,
            'end'   => $end
        ];
    }

}
