<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Nov 2023 20:45:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\HumanResources\Clocking\ClockingTypeEnum;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineStatusEnum;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use App\Enums\HumanResources\Workplace\WorkplaceTypeEnum;
use App\Enums\Miscellaneous\GenderEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasHumanResourcesStats
{
    public function getJobPositionsStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_job_positions')->default(0);

        return $table;
    }

    public function getWorkplaceFieldStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_workplaces')->default(0);
        foreach (WorkplaceTypeEnum::cases() as $case) {
            $table->unsignedSmallInteger('number_workplaces_type_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function getEmployeeFieldStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_employees')->default(0);
        $table->unsignedSmallInteger('number_employees_currently_working')->default(0);

        foreach (EmployeeStateEnum::cases() as $case) {
            $table->unsignedSmallInteger('number_employees_state_'.$case->snake())->default(0);
        }
        foreach (EmployeeTypeEnum::cases() as $case) {
            $table->unsignedSmallInteger('number_employees_type_'.$case->snake())->default(0);
        }

        foreach (GenderEnum::cases() as $case) {
            $table->unsignedSmallInteger('number_employees_gender_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function getClockingMachinesFieldStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_clocking_machines')->default(0);

        foreach (ClockingMachineTypeEnum::cases() as $case) {
            $table->unsignedSmallInteger('number_clocking_machines_type_'.$case->snake())->default(0);
        }

        foreach (ClockingMachineStatusEnum::cases() as $case) {
            $table->unsignedSmallInteger('number_clocking_machines_status_'.$case->snake())->default(0);
        }

        return $table;

    }

    public function getClockingsFieldStats(Blueprint $table): Blueprint
    {
        $table->dateTimeTz('last_clocking_at')->nullable();
        $table->unsignedInteger('number_clockings')->default(0);
        foreach (ClockingTypeEnum::cases() as $case) {
            $table->unsignedInteger('number_clockings_type_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function getTimesheetsStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_timesheets')->default(0);

        return $table;
    }

    public function getTimeTrackersStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_time_trackers')->default(0);
        foreach (TimeTrackerStatusEnum::cases() as $case) {
            $table->unsignedInteger('number_time_trackers_status_'.$case->snake())->default(0);
        }

        return $table;
    }
}
