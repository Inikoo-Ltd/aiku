<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Mar 2023 04:45:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\DateIntervals\PreviousQuartersEnum;
use App\Enums\DateIntervals\PreviousYearsEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasDateIntervalsStats
{
    public function decimalDateIntervals(Blueprint $table, array $subjects = []): Blueprint
    {
        foreach ($subjects as $subject) {
            $subject = $subject ? $subject.'_' : '';

            foreach (DateIntervalEnum::values() as $col) {
                $table->decimal($subject.$col, 16)->default(0);
            }

            foreach (DateIntervalEnum::lastYearValues() as $col) {
                $table->decimal($subject.$col.'_ly', 16)->default(0);
            }
            foreach (PreviousYearsEnum::values() as $col) {
                $table->decimal($subject.$col, 16)->default(0);
            }
            foreach (PreviousQuartersEnum::values() as $col) {
                $table->decimal($subject.$col, 16)->default(0);
            }
        }

        return $table;
    }

    public function unsignedIntegerDateIntervals(Blueprint $table, array $subjects = []): Blueprint
    {
        foreach ($subjects as $subject) {
            $subject = $subject ? $subject.'_' : '';

            foreach (DateIntervalEnum::values() as $col) {
                $table->unsignedInteger($subject.$col)->default(0);
            }
            foreach (DateIntervalEnum::lastYearValues() as $col) {
                $table->unsignedInteger($subject.$col.'_ly')->default(0);
            }
            foreach (PreviousYearsEnum::values() as $col) {
                $table->unsignedInteger($subject.$col)->default(0);
            }
            foreach (PreviousQuartersEnum::values() as $col) {
                $table->unsignedInteger($subject.$col)->default(0);
            }
        }

        return $table;
    }

    public function jsonDateIntervals(Blueprint $table, array $subjects = []): Blueprint
    {
        foreach ($subjects as $subject) {
            $subject = $subject ? $subject.'_' : '';

            foreach (DateIntervalEnum::values() as $col) {
                $table->jsonb($subject.$col)->nullable();
            }
            foreach (DateIntervalEnum::lastYearValues() as $col) {
                $table->jsonb($subject.$col.'_ly')->nullable();
            }
            foreach (PreviousYearsEnum::values() as $col) {
                $table->jsonb($subject.$col)->nullable();
            }
            foreach (PreviousQuartersEnum::values() as $col) {
                $table->jsonb($subject.$col)->nullable();
            }
        }

        return $table;
    }
}
