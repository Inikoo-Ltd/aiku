<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 12:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseTeam\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWarehouseTeamAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\UI\Inventory\WarehouseTeamTabsEnum;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWarehouseTeam extends OrgAction
{
    use WithWarehouseTeamAuthorisation;

    private string $period = 'today';

    private Carbon $clockingsDay;

    /**
     * Employees still working here with a warehouse job position for this warehouse
     * (or one not tied to any warehouse).
     */
    public static function teamQuery(Warehouse $warehouse): Builder
    {
        return Employee::query()
            ->where('employees.organisation_id', $warehouse->organisation_id)
            ->whereIn('employees.state', [EmployeeStateEnum::WORKING, EmployeeStateEnum::LEAVING])
            ->whereHas('jobPositions', function ($query) use ($warehouse) {
                $query->where('job_positions.department', 'warehouse')
                    ->where(function ($query) use ($warehouse) {
                        $query->whereJsonContains('employee_has_job_positions.scopes->Warehouse', $warehouse->id)
                            ->orWhereNull('employee_has_job_positions.scopes->Warehouse');
                    });
            });
    }

    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Warehouse
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(WarehouseTeamTabsEnum::values());

        $period       = $request->input('period');
        $this->period = in_array($period, GetWarehouseTeamDashboard::PERIODS, true) ? $period : 'today';

        $timezone           = $warehouse->organisation->timezone->name ?? 'UTC';
        $day                = $request->input('date');
        $this->clockingsDay = $day && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)
            ? Carbon::createFromFormat('Y-m-d', $day, $timezone)->startOfDay()
            : Carbon::today($timezone);

        return $this->handle($warehouse);
    }

    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        $dashboard = fn () => GetWarehouseTeamDashboard::run($warehouse, $this->period);
        $clockings = fn () => GetWarehouseTeamClockings::run($warehouse, $this->clockingsDay);

        return Inertia::render(
            'Org/Warehouse/WarehouseTeam',
            [
                'breadcrumbs'           => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'                 => __('Warehouse team'),
                'pageHead'              => [
                    'title' => __('Warehouse team'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-user-hard-hat'],
                        'title' => __('Warehouse team'),
                    ],
                ],
                'tabs'                  => [
                    'current'    => $this->tab,
                    'navigation' => WarehouseTeamTabsEnum::navigation(),
                ],
                'timezone'              => $warehouse->organisation->timezone->name ?? 'UTC',
                'team_members'          => self::teamQuery($warehouse)->orderBy('contact_name')->get(['employees.id', 'employees.contact_name', 'employees.alias'])
                    ->map(fn (Employee $employee) => ['id' => $employee->id, 'name' => $employee->contact_name ?: $employee->alias])->values(),

                WarehouseTeamTabsEnum::DASHBOARD->value => $this->tab == WarehouseTeamTabsEnum::DASHBOARD->value ? $dashboard : Inertia::optional($dashboard),
                WarehouseTeamTabsEnum::CLOCKINGS->value => $this->tab == WarehouseTeamTabsEnum::CLOCKINGS->value ? $clockings : Inertia::optional($clockings),

                'backlog_route'         => [
                    'name'       => 'grp.org.warehouses.show.dispatching.backlog',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'store_clocking_route'  => [
                    'name'       => 'grp.models.warehouse.team.clocking.store',
                    'parameters' => ['warehouse' => $this->warehouse->id],
                ],
                'update_clocking_route' => [
                    'name'       => 'grp.models.warehouse.team.clocking.update',
                    'parameters' => ['warehouse' => $this->warehouse->id],
                ],
                'delete_clocking_route' => [
                    'name'       => 'grp.models.warehouse.team.clocking.delete',
                    'parameters' => ['warehouse' => $this->warehouse->id],
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.team.dashboard',
                            'parameters' => $routeParameters,
                        ],
                        'icon'  => ['fal', 'fa-user-hard-hat'],
                        'label' => __('Team'),
                    ],
                ],
            ]
        );
    }
}
