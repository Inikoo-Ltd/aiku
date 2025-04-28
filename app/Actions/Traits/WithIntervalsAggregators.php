<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Apr 2024 11:40:48 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\DateIntervals\PreviousQuartersEnum;
use App\Enums\DateIntervals\PreviousYearsEnum;

trait WithIntervalsAggregators
{
    public function getIntervalsData(array $stats, $queryBase, $statField, $dateField = 'date', $sumField = 'sum_aggregate', ?array $intervals = null, ?array $doPreviousPeriods = null): array
    {
        $stats = array_merge($stats, $this->getIntervalStats($queryBase, $statField, $dateField, $sumField, $intervals));
        $stats = array_merge($stats, $this->getLastYearIntervalStats($queryBase, $statField, $dateField, $sumField, $intervals));


        if ($doPreviousPeriods === null || in_array('previous_years', $doPreviousPeriods)) {
            $stats = array_merge($stats, $this->getPreviousYearsIntervalStats($queryBase, $statField, $dateField, $sumField));
        }

        if ($doPreviousPeriods === null || in_array('previous_quarters', $doPreviousPeriods)) {
            $stats = array_merge($stats, $this->getPreviousQuartersIntervalStats($queryBase, $statField, $dateField, $sumField));
        }

        return $stats;
    }

    public function getIntervalStats(
        $queryBase,
        string $statField,
        string $dateField = 'date',
        string $sumField = 'sum_aggregate',
        ?array $intervals = null
    ): array {
        $stats = [];


        foreach (DateIntervalEnum::cases() as $interval) {
            if ($intervals !== null && !in_array($interval, $intervals)) {
                continue;
            }

            $query = $queryBase->clone();
            $query = $interval->wherePeriod($query, $dateField);

            $res                                = $query->first();
            $stats[$statField.$interval->value] = $res->{$sumField} ?? 0;
        }

        return $stats;
    }

    public function getLastYearIntervalStats(
        $queryBase,
        string $statField,
        string $dateField = 'date',
        string $sumField = 'sum_aggregate',
        ?array $intervals = null
    ): array {
        $stats = [];
        foreach (DateIntervalEnum::cases() as $interval) {
            if ($interval == DateIntervalEnum::ALL) {
                continue;
            }

            if ($intervals !== null && !in_array($interval, $intervals)) {
                continue;
            }

            $query = $queryBase->clone();
            if ($query = $interval->whereLastYearPeriod($query, $dateField)) {
                $res                                      = $query->first();
                $stats[$statField.$interval->value.'_ly'] = $res->{$sumField} ?? 0;
            }
        }


        return $stats;
    }

    public function getPreviousYearsIntervalStats(
        $queryBase,
        string $statField,
        string $dateField = 'date',
        string $sumField = 'sum_aggregate'
    ): array {
        $stats = [];
        foreach (PreviousYearsEnum::cases() as $period) {
            $query = $queryBase->clone();
            $query = $period->wherePeriod($query, $dateField);

            $res                              = $query->first();
            $stats[$statField.$period->value] = $res->{$sumField} ?? 0;
        }


        return $stats;
    }

    public function getPreviousQuartersIntervalStats(
        $queryBase,
        string $statField,
        string $dateField = 'date',
        string $sumField = 'sum_aggregate'
    ): array {
        $stats = [];
        foreach (PreviousQuartersEnum::cases() as $period) {
            $query = $queryBase->clone();
            if ($query = $period->wherePeriod($query, $dateField)) {
                $res                              = $query->first();
                $stats[$statField.$period->value] = $res->{$sumField} ?? 0;
            }
        }


        return $stats;
    }

}
