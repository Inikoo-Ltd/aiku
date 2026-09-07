<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Sep 2026 10:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Production\UI;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowArtisansDashboard extends OrgAction
{
    public function handle(Production $production): Production
    {
        return $production;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production);
    }

    public function htmlResponse(Production $production, ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Production/ArtisansDashboard',
            [
                'title'       => __('Artisans'),
                'breadcrumbs' => $this->getBreadcrumbs($routeParameters),
                'pageHead'    => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-hat-chef'],
                        'title' => __('Artisans')
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('Artisans dashboard')
                    ],
                    'title'     => __('Artisans'),
                ],
                'floor_route' => [
                    'name'       => 'grp.org.productions.show.floor',
                    'parameters' => $routeParameters
                ],
                'performance_route' => [
                    'name'       => 'grp.org.productions.show.artisans.index',
                    'parameters' => $routeParameters
                ],
                'payroll_export_route' => [
                    'name'       => 'grp.org.productions.show.artisans.payroll.export',
                    'parameters' => $routeParameters
                ],
                'artisans' => $this->artisans($production),
            ]
        );
    }

    /**
     * @return array<int, array{id: int, name: string, queued: int, working_now: bool}>
     */
    private function artisans(Production $production): array
    {
        $artisanIds = DB::table('artisan_assignments')->pluck('employee_id')
            ->merge(JobOrder::where('production_id', $production->id)->whereNotNull('employee_id')->pluck('employee_id'))
            ->unique();

        $queued = JobOrderItemTask::where('job_order_item_tasks.production_id', $production->id)
            ->where('job_order_item_tasks.state', '!=', JobOrderItemTaskStateEnum::DONE)
            ->join('job_orders', 'job_orders.id', '=', 'job_order_item_tasks.job_order_id')
            ->where('job_orders.state', JobOrderStateEnum::CONFIRMED)
            ->whereNotNull('job_orders.employee_id')
            ->groupBy('job_orders.employee_id')
            ->selectRaw('job_orders.employee_id, count(*) as queued')
            ->pluck('queued', 'employee_id');

        $workingNow = ManufactureTaskSession::where('production_id', $production->id)
            ->where('state', ManufactureTaskSessionStateEnum::OPEN)
            ->pluck('employee_id');

        return Employee::where('organisation_id', $production->organisation_id)
            ->where('state', EmployeeStateEnum::WORKING)
            ->whereIn('id', $artisanIds)
            ->orderBy('contact_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id'          => $employee->id,
                'name'        => $employee->contact_name,
                'queued'      => (int)$queued->get($employee->id, 0),
                'working_now' => $workingNow->contains($employee->id),
            ])
            ->sortBy('queued')
            ->values()
            ->all();
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $routeParameters = Arr::only($routeParameters, ['organisation', 'production']);

        return array_merge(
            (new ShowOperationsDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.artisans.dashboard',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Artisans'),
                    ],
                    'suffix' => $suffix,
                ],
            ]
        );
    }
}
