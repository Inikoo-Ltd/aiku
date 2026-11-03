<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artisan;

use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\Production;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Who makes a department's product when nobody is chosen: of the working artisans on the roster in the department's work area, those clocked in today
 * first, then those on the tablet in the last week, then the fewest open job orders (the number the pickers show), then
 * the name. An artisan neither clocked in today nor on the tablet in the last week is never picked.
 */
class PickDepartmentArtisans
{
    use AsObject;

    /**
     * @return array<int, int> employee id keyed by department id
     */
    public function handle(Production $production): array
    {
        $today = now($production->organisation->timezone?->name ?? config('app.timezone'))->toDateString();

        return DB::table('artisan_assignments')
            ->join('artefact_departments', 'artefact_departments.work_area_id', 'artisan_assignments.artisanable_id')
            ->join('employees', 'employees.id', 'artisan_assignments.employee_id')
            ->where('artisan_assignments.artisanable_type', 'WorkArea')
            ->where('artefact_departments.production_id', $production->id)
            ->whereNull('artefact_departments.deleted_at')
            ->where('employees.state', EmployeeStateEnum::WORKING->value)
            ->whereNotIn('employees.id', Arr::get($production->data, 'hidden_artisan_ids', []))
            ->get([
                'artefact_departments.id as department_id',
                'employees.id as employee_id',
                'employees.contact_name',
                DB::raw("exists(select 1 from timesheets where timesheets.subject_type = 'Employee' and timesheets.subject_id = employees.id and timesheets.date = '$today') as clocked_in"),
                DB::raw('exists(select 1 from manufacture_task_sessions where manufacture_task_sessions.employee_id = employees.id and manufacture_task_sessions.production_id = '.(int) $production->id." and manufacture_task_sessions.started_at > now() - interval '7 days') as on_tablet_lately"),
                DB::raw('(select count(*) from job_orders where job_orders.employee_id = employees.id and job_orders.production_id = '.(int) $production->id.' and job_orders.deleted_at is null and job_orders.state in ('.collect(self::openJobOrderStates())->map(fn (string $state) => "'$state'")->implode(',').')) as open_job_orders'),
            ])
            ->filter(fn ($artisan) => $artisan->clocked_in || $artisan->on_tablet_lately)
            ->groupBy('department_id')
            ->map(fn (Collection $artisans) => $artisans
                ->sortBy([
                    fn ($a, $b) => $b->clocked_in <=> $a->clocked_in,
                    fn ($a, $b) => $a->open_job_orders <=> $b->open_job_orders,
                    fn ($a, $b) => strnatcasecmp($a->contact_name, $b->contact_name),
                ])
                ->first()->employee_id)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function openJobOrderStates(): array
    {
        return [JobOrderStateEnum::IN_PROCESS->value, JobOrderStateEnum::SUBMITTED->value, JobOrderStateEnum::CONFIRMED->value];
    }

    /**
     * The pick as SQL on artefact_departments.id, for queries that join the maker.
     *
     * @param  array<int, int>  $picks
     */
    public static function sql(array $picks): string
    {
        if (!$picks) {
            return 'null::bigint';
        }

        return 'case artefact_departments.id '.collect($picks)->map(fn (int $employeeId, int $departmentId) => 'when '.$departmentId.' then '.$employeeId)->implode(' ').' end';
    }
}
