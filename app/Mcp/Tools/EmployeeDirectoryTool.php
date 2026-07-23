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

#[Description('Search employees in an organisation by name, worker number or job title. Returns work contact info and employment state, never salary or personal data.')]
class EmployeeDirectoryTool extends AikuOrganisationTool
{
    protected function permission(): OrganisationPermissionsEnum
    {
        return OrganisationPermissionsEnum::HUMAN_RESOURCES_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'organisation' => ['required', 'string'],
            'query'        => ['required', 'string'],
        ]);

        $organisation = $this->authorisedOrganisation($request);
        if (!$organisation) {
            return Response::error('Organisation not found or permission denied.');
        }

        $query   = $request->string('query');
        $pattern = "%{$query}%";

        $employees = Employee::where('organisation_id', $organisation->id)
            ->where(function ($q) use ($pattern) {
                $q->whereRaw('contact_name COLLATE "C" ILIKE ?', [$pattern])
                    ->orWhereRaw('worker_number COLLATE "C" ILIKE ?', [$pattern])
                    ->orWhereRaw('job_title COLLATE "C" ILIKE ?', [$pattern]);
            })
            ->limit(20)
            ->get(['slug', 'contact_name', 'worker_number', 'job_title', 'work_email', 'state', 'employment_start_at']);

        $results = $employees->map(function ($employee) {
            return [
                'slug'                 => $employee->slug,
                'contact_name'         => $employee->contact_name,
                'worker_number'        => $employee->worker_number,
                'job_title'            => $employee->job_title,
                'work_email'           => $employee->work_email,
                'state'                => $employee->state->value,
                'employment_start_at'  => $employee->employment_start_at?->format('Y-m-d'),
            ];
        });

        return Response::json([
            'organisation' => $organisation->slug,
            'query'        => $query,
            'results'      => $results,
            'count'        => $results->count(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organisation' => $schema->string()->description('Organisation slug')->required(),
            'query'        => $schema->string()->description('Search text (name, worker number, or job title)')->required(),
        ];
    }
}
