<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\MaterialRequest\UI;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\UI\IndexJobOrderPlan;
use App\Actions\Production\MaterialRequest\GetDayMaterialsChecklist;
use App\Actions\Production\MaterialRequest\MaterialsCheckAuthorisation;
use App\Actions\Production\Production\UI\ShowOperationsDashboard;
use App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestStateEnum;
use App\Enums\Production\ProductionMaterialRequestItem\ProductionMaterialRequestItemStateEnum;
use App\Http\Resources\Production\DayMaterialsChecklistResource;
use App\InertiaTable\InertiaTable;
use App\Models\Production\Production;
use App\Models\Production\ProductionMaterialRequest;
use App\Models\Production\ProductionMaterialRequestItem;
use App\Models\Production\RawMaterial;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexDayMaterialsCheck extends OrgAction
{
    private string $date;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.edit",
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $request->query('date'));
    }

    public function handle(Production $production, ?string $date = null): LengthAwarePaginator
    {
        $this->date = IndexJobOrderPlan::parseDate($date, $production);

        return QueryBuilder::for(RawMaterial::query()->withoutGlobalScopes()->fromSub(GetDayMaterialsChecklist::make()->query($production, $this->date), 'raw_materials'))
            ->defaultSort('code')
            ->allowedSorts(['code', 'needed_units'])
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withEmptyState([
                    'title'       => __('No materials needed for this day'),
                    'description' => __('Materials come from the recipes of the jobs on the day plan'),
                ])
                ->column(key: 'code', label: __('Material'), canBeHidden: false, sortable: true)
                ->column(key: 'job_orders', label: __('For jobs'), canBeHidden: false)
                ->column(key: 'needed_units', label: __('Needed'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'at_factory_units', label: __('At the factory'), canBeHidden: false, align: 'right')
                ->column(key: 'check', label: __('Check'), canBeHidden: false)
                ->defaultSort('code');
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function requestsOfTheDay(Production $production): array
    {
        return ProductionMaterialRequest::where('production_id', $production->id)
            ->whereDate('plan_date', $this->date)
            ->whereIn('state', [ProductionMaterialRequestStateEnum::SUBMITTED, ProductionMaterialRequestStateEnum::DELIVERED])
            ->with(['items.rawMaterial', 'submittedBy'])
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (ProductionMaterialRequest $request) => [
                'id'           => $request->id,
                'state'        => $request->state->value,
                'state_label'  => $request->state->labels()[$request->state->value],
                'submitted_at' => $request->submitted_at,
                'submitted_by' => $request->submittedBy?->contact_name,
                'items'        => $request->items->map(fn (ProductionMaterialRequestItem $item) => [
                    'id'             => $item->id,
                    'code'           => $item->rawMaterial->code,
                    'boxes'          => $item->boxes,
                    'box_units'      => (float) $item->box_units,
                    'skos_to_bring'  => (float) $item->skos_to_bring,
                    'delivered_skos' => (float) $item->delivered_skos,
                    'state'          => $item->state->value,
                    'state_label'    => ProductionMaterialRequestItemStateEnum::labels()[$item->state->value],
                ])->all(),
            ])
            ->all();
    }

    public function htmlResponse(LengthAwarePaginator $lines, ActionRequest $request): Response
    {
        $production = $this->production;
        $canManage  = MaterialsCheckAuthorisation::canManageMaterialsCheck($request->user(), $production);
        $checking   = MaterialsCheckAuthorisation::checkingRequest($production, $this->date);

        return Inertia::render(
            'Org/Production/DayMaterialsCheck',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Materials check'),
                'pageHead'    => [
                    'title' => __('Materials check'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-clipboard-check'],
                        'title' => __('Materials check'),
                    ],
                ],
                'production_id'          => $production->id,
                'date'                   => $this->date,
                'today'                  => IndexJobOrderPlan::today($production),
                'factory_location'       => $production->factoryLocation?->code,
                'can_check'              => (bool) $production->factory_location_id && MaterialsCheckAuthorisation::canDoMaterialsCheck($request->user(), $production, $this->date),
                'can_manage'             => $canManage && (bool) $production->factory_location_id,
                'checker_employee_id'    => $checking?->checker_employee_id,
                'checker'                => $checking?->checker?->contact_name,
                'operators'              => $canManage ? IndexJobOrderPlan::make()->getOperators($production) : [],
                'jobs_without_materials' => GetDayMaterialsChecklist::make()->jobsWithoutMaterials($production, $this->date),
                'requests'               => $this->requestsOfTheDay($production),
                'data'                   => DayMaterialsChecklistResource::collection($lines),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $routeParameters = Arr::only($routeParameters, ['organisation', 'production']);

        return array_merge(
            (new ShowOperationsDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.operations.materials_check',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Materials check'),
                    ],
                ],
            ]
        );
    }
}
