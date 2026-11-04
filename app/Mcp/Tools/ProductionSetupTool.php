<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Production\Artisan\AttachArtisan;
use App\Actions\Production\WorkArea\StoreWorkArea;
use App\Actions\Production\WorkArea\UpdateWorkArea;
use App\Actions\Production\WorkArea\WithWorkAreaAuthorisation;
use App\Actions\SysAdmin\McpChange\GetMcpChangeSnapshot;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Location;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\Production;
use App\Models\Production\WorkArea;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Factory location and work areas are the production manager's (organisation admin) to set, as on the Artisans
 * dashboard; the tool also needs can_use_mcp_production (INI-205).
 */
#[Description('Shows or sets up where a production works: its factory location (the warehouse location where raw materials for the factory are delivered) and its work areas (where artisans work, with the product departments made there and the artisans who work there). Without changes it shows them, with every department and the workers of the organisation. factory_location takes a location code, or null to clear it. work_areas is a list of areas by name: an area not there yet is created; rename renames it; departments (department codes) and artisans (worker names, aliases or worker numbers) replace that area\'s list when given. A department is made in one area, so giving it to an area takes it out of another. Codes and names are checked before anything is saved, and nothing is saved when any part fails. Only save after showing the user the change and they confirmed in their own words, passing their request text. Logged in AI changes and can be reverted there. Only for production managers enrolled to set up production through their assistant.')]
class ProductionSetupTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;
    use WithMcpProduction;

    public function handle(Request $request): Response
    {
        $request->validate([
            'production'               => ['required', 'string'],
            'factory_location'         => ['sometimes', 'nullable', 'string'],
            'work_areas'               => ['sometimes', 'array', 'min:1', 'max:30'],
            'work_areas.*.name'        => ['required', 'string', 'max:255'],
            'work_areas.*.rename'      => ['sometimes', 'string', 'max:255'],
            'work_areas.*.departments' => ['sometimes', 'array'],
            'work_areas.*.artisans'    => ['sometimes', 'array'],
            'request_text'             => ['required_with:factory_location,work_areas', 'string', 'max:4000'],
        ]);

        $production = $this->resolveProduction($request);
        if ($production instanceof Response) {
            return $production;
        }

        $target = ['production_id' => $production->id, 'code' => $production->code];

        setPermissionsTeamId($request->user()->group_id);
        if (!WithWorkAreaAuthorisation::canManageWorkAreas($request->user(), $production)) {
            return Response::error("The factory location and work areas of $production->code are set by its production manager (an organisation admin). Nothing was changed.");
        }

        if (!$request->has('factory_location') && !$request->has('work_areas')) {
            return Response::json($this->show($production, $target));
        }

        try {
            $plan = $this->plan($production, $request);
        } catch (ValidationException $exception) {
            return Response::error('Nothing was changed. '.implode(' ', $exception->validator->errors()->all()));
        }

        try {
            $this->recordChange(
                $request,
                McpChangeTypeEnum::PRODUCTION_SETUP,
                $production->code.': '.collect([array_key_exists('factory_location_id', $plan) ? 'factory location' : null, $plan['areas'] ? 'work areas' : null])->filter()->implode(', '),
                $target,
                fn () => DB::transaction(fn () => $this->apply($production, $plan))
            );
        } catch (ValidationException $exception) {
            return Response::error('Nothing was changed. '.implode(' ', $exception->validator->errors()->all()));
        }

        return Response::json([
            'changed'       => (bool) $this->mcpChange,
            'change_log_id' => $this->mcpChange?->id,
            'before'        => $this->mcpChange?->data['before_text'],
            'after'         => $this->mcpChange?->data['after_text'],
        ]);
    }

    private function show(Production $production, array $target): array
    {
        return [
            'production'  => $production->code,
            'setup'       => GetMcpChangeSnapshot::make()->describe(McpChangeTypeEnum::PRODUCTION_SETUP, $target, GetMcpChangeSnapshot::run(McpChangeTypeEnum::PRODUCTION_SETUP, $target)),
            'departments' => ArtefactDepartment::where('production_id', $production->id)->orderBy('code')->get(['code', 'name', 'work_area_id'])
                ->map(fn (ArtefactDepartment $department) => ['code' => $department->code, 'name' => $department->name, 'has_area' => (bool) $department->work_area_id])->all(),
            'workers'     => Employee::where('organisation_id', $production->organisation_id)->where('state', EmployeeStateEnum::WORKING)->orderBy('contact_name')
                ->get(['contact_name', 'alias', 'worker_number'])
                ->map(fn (Employee $employee) => array_filter(['name' => $employee->contact_name, 'alias' => $employee->alias, 'worker_number' => $employee->worker_number]))->all(),
        ];
    }

    /**
     * Resolves every code and name first, so a typo anywhere saves nothing.
     *
     * @throws ValidationException
     */
    private function plan(Production $production, Request $request): array
    {
        $plan   = ['areas' => []];
        $errors = [];

        if ($request->has('factory_location')) {
            $code = $request->get('factory_location');
            if ($code === null || trim($code) === '') {
                $plan['factory_location_id'] = null;
            } else {
                $locations = Location::where('organisation_id', $production->organisation_id)->whereRaw('lower(code) = ?', [strtolower(trim($code))])->get(['id']);
                if ($locations->count() !== 1) {
                    $errors[] = $locations->isEmpty() ? "There is no location $code in {$production->organisation->code}." : "Location $code is in several warehouses of {$production->organisation->code}; set it on screen.";
                } else {
                    $plan['factory_location_id'] = $locations->first()->id;
                }
            }
        }

        $departments = ArtefactDepartment::where('production_id', $production->id)->get(['id', 'code'])->keyBy(fn (ArtefactDepartment $department) => strtolower($department->code));
        $employees   = Employee::where('organisation_id', $production->organisation_id)->where('state', EmployeeStateEnum::WORKING)->get(['id', 'contact_name', 'alias', 'worker_number']);
        $claimed     = [];

        foreach ($request->get('work_areas', []) as $area) {
            $workArea = WorkArea::where('production_id', $production->id)->whereRaw('lower(name) = ?', [strtolower(trim($area['name']))])->first();
            $areaPlan = ['work_area_id' => $workArea?->id, 'name' => $workArea ? ($area['rename'] ?? null) : ($area['rename'] ?? trim($area['name']))];

            if (array_key_exists('departments', $area)) {
                $areaPlan['department_ids'] = [];
                foreach ($area['departments'] as $code) {
                    $department = $departments->get(strtolower(trim($code)));
                    if (!$department) {
                        $errors[] = "There is no department $code in $production->code. Departments: ".$departments->pluck('code')->implode(', ').'.';
                    } elseif (isset($claimed[$department->id])) {
                        $errors[] = "Department $department->code is given to two areas; a department is made in one area.";
                    } else {
                        $claimed[$department->id]     = true;
                        $areaPlan['department_ids'][] = $department->id;
                    }
                }
            }

            if (array_key_exists('artisans', $area)) {
                $areaPlan['artisan_ids'] = [];
                foreach ($area['artisans'] as $given) {
                    $needle  = strtolower(trim($given));
                    $matches = $employees->filter(fn (Employee $employee) => in_array($needle, array_map('strtolower', array_filter([$employee->contact_name, $employee->alias, $employee->worker_number])), true));
                    if ($matches->count() !== 1) {
                        $errors[] = $matches->isEmpty()
                            ? "No working employee of {$production->organisation->code} is called $given."
                            : "$given matches several employees (".$matches->pluck('contact_name')->implode(', ').'): use the alias or worker number.';
                    } else {
                        $areaPlan['artisan_ids'][] = $matches->first()->id;
                    }
                }
            }

            $plan['areas'][] = $areaPlan;
        }

        if ($errors) {
            throw ValidationException::withMessages(['plan' => $errors]);
        }

        return $plan;
    }

    private function apply(Production $production, array $plan): void
    {
        if (array_key_exists('factory_location_id', $plan)) {
            $production->update(['factory_location_id' => $plan['factory_location_id']]);
        }

        foreach ($plan['areas'] as $area) {
            $workArea = $area['work_area_id']
                ? WorkArea::findOrFail($area['work_area_id'])
                : StoreWorkArea::make()->action($production, ['name' => $area['name']]);

            $changes = array_filter([
                'name'                    => $area['work_area_id'] ? $area['name'] : null,
                'artefact_department_ids' => $area['department_ids'] ?? null,
            ], fn ($value) => $value !== null);
            if ($changes) {
                UpdateWorkArea::make()->action($workArea, $changes);
            }

            if (array_key_exists('artisan_ids', $area)) {
                $current = $workArea->artisans()->pluck('employees.id')->all();
                if ($leaving = array_diff($current, $area['artisan_ids'])) {
                    $workArea->artisans()->detach($leaving);
                }
                foreach (array_diff($area['artisan_ids'], $current) as $employeeId) {
                    AttachArtisan::make()->handle($workArea, ['employee_id' => $employeeId]);
                }
            }
        }
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'production'       => $schema->string()->description('Production code or slug, e.g. AWGp')->required(),
            'factory_location' => $schema->string()->description('Location code where raw materials for the factory are delivered; null clears it'),
            'work_areas'       => $schema->array()->items($schema->object([
                'name'        => $schema->string()->required(),
                'rename'      => $schema->string(),
                'departments' => $schema->array()->items($schema->string()),
                'artisans'    => $schema->array()->items($schema->string()),
            ]))->description('Areas to create or change: [{"name": "Oils Area", "departments": ["EO"], "artisans": ["Jana Novak"]}]'),
            'request_text'     => $schema->string()->description('The user\'s request, verbatim; required when saving'),
        ];
    }
}
