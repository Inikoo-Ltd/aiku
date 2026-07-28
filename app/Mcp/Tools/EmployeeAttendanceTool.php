<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Models\HumanResources\Employee;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Attendance and timesheet summary for one employee over a date range. Returns days with timesheets and total working duration.')]
#[IsReadOnly]
class EmployeeAttendanceTool extends AikuOrganisationTool
{
    protected function permission(): OrganisationPermissionsEnum
    {
        return OrganisationPermissionsEnum::HUMAN_RESOURCES_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation' => ['required', 'string'],
            'employee'     => ['required', 'string'],
            'from'         => ['required', 'date'],
            'to'           => ['required', 'date', 'after_or_equal:from'],
        ]);

        $organisation = $this->authorisedOrganisation($request);
        if (!$organisation) {
            return Response::error('No organisation matching that name is available to you. Organisations are identified by a short slug or code (for example aw, sk, es) — not by their full display name. Call my-access-tool to list the organisations you can query, and use a value from there. Do not guess.');
        }

        $employee = Employee::where('slug', $request->string('employee'))
            ->where('organisation_id', $organisation->id)
            ->first();

        if (!$employee) {
            return Response::error('Employee not found.');
        }

        $timesheets = $employee->timesheets()
            ->whereBetween('date', [$request->date('from'), $request->date('to')])
            ->selectRaw('count(*) as days_count, coalesce(sum(working_duration), 0) as total_working_seconds')
            ->first();

        $totalWorkingHours = $timesheets->total_working_seconds > 0
            ? round($timesheets->total_working_seconds / 3600, 1)
            : 0;

        return Response::json([
            'employee'               => $employee->contact_name,
            'from'                   => $request->string('from'),
            'to'                     => $request->string('to'),
            'days_with_timesheet'    => (int) $timesheets->days_count,
            'total_working_hours'    => $totalWorkingHours,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Organisation slug or code')->required(),
            'employee'     => $schema->string()->description('Employee slug')->required(),
            'from'         => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'           => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
        ];
    }
}
