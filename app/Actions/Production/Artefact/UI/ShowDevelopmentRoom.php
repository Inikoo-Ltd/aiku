<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowCraftsDashboard;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\UI\Production\DevelopmentRoomTabsEnum;
use App\Http\Resources\Production\DevelopmentArtefactsResource;
use App\Http\Resources\Production\TrialRunsResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Employee;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The development team's room: the experiments they are working on, the trial runs the floor makes for
 * them, what is ready to launch, and what was launched or given up. Nothing here is sold or ordered until it
 * is launched.
 */
class ShowDevelopmentRoom extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.view"]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request)->withTab(DevelopmentRoomTabsEnum::values());

        return $production;
    }

    public function artefacts(Production $production, ArtefactStateEnum|string $scope, string $prefix): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query->whereWith('artefacts.code', $value)->orWhereWith('artefacts.name', $value));
        });

        $productOnSale = DB::table('product_has_org_stocks')
            ->join('products', 'products.id', 'product_has_org_stocks.product_id')
            ->whereColumn('product_has_org_stocks.org_stock_id', 'artefacts.org_stock_id')
            ->where('products.state', ProductStateEnum::ACTIVE->value)
            ->where('products.is_for_sale', true)
            ->selectRaw('count(*) > 0');

        $trialRuns = fn (?JobOrderStateEnum $state) => DB::table('job_order_items')
            ->join('job_orders', 'job_orders.id', 'job_order_items.job_order_id')
            ->whereColumn('job_order_items.artefact_id', 'artefacts.id')
            ->when($state, fn ($query) => $query->where('job_orders.state', $state->value))
            ->selectRaw('count(distinct job_orders.id)');

        $query = QueryBuilder::for(Artefact::class)
            ->where('artefacts.production_id', $production->id)
            ->leftJoin('org_stocks', 'artefacts.org_stock_id', 'org_stocks.id');

        if ($scope === DevelopmentRoomTabsEnum::LAUNCHED->value) {
            $query->whereIn('artefacts.state', ArtefactStateEnum::launched())->whereNotNull('artefacts.data->launch');
        } else {
            $query->where('artefacts.state', $scope);
        }

        return $query
            ->defaultSort($scope === DevelopmentRoomTabsEnum::LAUNCHED->value ? '-launched_at' : 'code')
            ->select([
                'artefacts.id',
                'artefacts.slug',
                'artefacts.code',
                'artefacts.name',
                'artefacts.state',
                'artefacts.data',
                'org_stocks.code as org_stock_code',
            ])
            ->selectRaw("artefacts.data->'launch'->>'at' as launched_at")
            ->selectRaw('(select count(*) from artefacts_manufacture_tasks where artefacts_manufacture_tasks.artefact_id = artefacts.id) as recipe_steps')
            ->selectSub($trialRuns(null), 'trial_runs')
            ->selectSub($trialRuns(JobOrderStateEnum::RECEIVED), 'trial_runs_received')
            ->selectSub($productOnSale, 'is_on_sale')
            ->allowedSorts(['code', 'name', 'launched_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function trialRuns(Production $production, string $prefix): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query->whereWith('job_orders.reference', $value)->orWhereWith('artefacts.code', $value));
        });

        return QueryBuilder::for(JobOrder::class)
            ->where('job_orders.production_id', $production->id)
            ->where('job_orders.data->trial_run', true)
            ->join('job_order_items', 'job_order_items.job_order_id', 'job_orders.id')
            ->join('artefacts', 'artefacts.id', 'job_order_items.artefact_id')
            ->leftJoin('employees', 'employees.id', 'job_orders.employee_id')
            ->defaultSort('-date')
            ->select([
                'job_orders.id',
                'job_orders.slug',
                'job_orders.reference',
                'job_orders.state',
                'job_orders.date',
                'job_orders.received_at',
                'artefacts.code as artefact_code',
                'artefacts.name as artefact_name',
                'artefacts.slug as artefact_slug',
                'job_order_items.quantity',
                'job_order_items.quantity_received',
                'employees.contact_name as artisan_name',
            ])
            ->allowedSorts(['reference', 'date', 'artefact_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function artefactsTableStructure(string $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table->name($prefix)->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->withLabelRecord([__('artefact'), __('artefacts')])
                ->withEmptyState(['title' => match ($prefix) {
                    DevelopmentRoomTabsEnum::LAUNCHED->value  => __('Nothing launched from this room yet'),
                    DevelopmentRoomTabsEnum::CANCELLED->value => __('No cancelled experiments'),
                    default                                   => __('No experiments yet'),
                }])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            match ($prefix) {
                DevelopmentRoomTabsEnum::LAUNCHED->value => $table
                    ->column(key: 'launched_at', label: __('Launched'), canBeHidden: false, sortable: true)
                    ->column(key: 'launched_by', label: __('By'), canBeHidden: false)
                    ->defaultSort('-launched_at'),
                DevelopmentRoomTabsEnum::CANCELLED->value => $table
                    ->column(key: 'cancelled_at', label: __('Cancelled'), canBeHidden: false)
                    ->column(key: 'cancelled_by', label: __('By'), canBeHidden: false)
                    ->column(key: 'cancelled_reason', label: __('Why'), canBeHidden: false)
                    ->defaultSort('code'),
                default => $table
                    ->column(key: 'recipe_steps', label: __('Recipe'), canBeHidden: false, align: 'right')
                    ->column(key: 'trial_runs', label: __('Trial runs'), canBeHidden: false, align: 'right')
                    ->column(key: 'org_stock_code', label: __('SKO'), canBeHidden: false)
                    ->column(key: 'is_on_sale', label: __('On sale'), canBeHidden: false, align: 'center')
                    ->defaultSort('code'),
            };

            if ($this->canEdit && $prefix === DevelopmentRoomTabsEnum::EXPERIMENTS->value) {
                $table->column(key: 'actions', label: '', canBeHidden: false, align: 'right');
            }
        };
    }

    public function trialRunsTableStructure(string $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table->name($prefix)->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->withLabelRecord([__('trial run'), __('trial runs')])
                ->withEmptyState(['title' => __('No trial runs yet')])
                ->column(key: 'reference', label: __('Job order'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'artefact_code', label: __('Experiment'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'quantity', label: __('Units'), canBeHidden: false, align: 'right')
                ->column(key: 'artisan_name', label: __('Artisan'), canBeHidden: false)
                ->column(key: 'state_label', label: __('State'), canBeHidden: false)
                ->column(key: 'date', label: __('Raised'), canBeHidden: false, sortable: true)
                ->column(key: 'received_at', label: __('At the office'), canBeHidden: false)
                ->defaultSort('-date');
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getStats(Production $production, array $routeParameters): array
    {
        $counts = ArtefactStateEnum::count($production);
        $tab    = fn (DevelopmentRoomTabsEnum $tab) => [
            'name'       => 'grp.org.productions.show.crafts.development',
            'parameters' => array_merge($routeParameters, ['tab' => $tab->value]),
        ];

        $openTrialRuns = JobOrder::where('production_id', $production->id)
            ->where('data->trial_run', true)
            ->whereIn('state', JobOrderStateEnum::open())
            ->count();

        $launchedThisYear = Artefact::where('production_id', $production->id)
            ->whereIn('state', ArtefactStateEnum::launched())
            ->whereRaw("(artefacts.data->'launch'->>'at')::timestamptz >= ?", [now()->startOfYear()])
            ->count();

        return [
            [
                'label' => __('Experiments'),
                'icon'  => 'fal fa-vial',
                'color' => '#f472b6',
                'value' => $counts[ArtefactStateEnum::IN_PROCESS->value],
                'route' => $tab(DevelopmentRoomTabsEnum::EXPERIMENTS),
            ],
            [
                'label' => __('Trial runs on the floor'),
                'icon'  => 'fal fa-flask',
                'color' => '#38bdf8',
                'value' => $openTrialRuns,
                'route' => $tab(DevelopmentRoomTabsEnum::TRIAL_RUNS),
            ],
            [
                'label' => __('Launched this year'),
                'icon'  => 'fal fa-rocket-launch',
                'color' => '#22c55e',
                'value' => $launchedThisYear,
                'route' => $tab(DevelopmentRoomTabsEnum::LAUNCHED),
            ],
            [
                'label' => __('Cancelled'),
                'icon'  => 'fal fa-ban',
                'color' => '#9ca3af',
                'value' => $counts[ArtefactStateEnum::CANCELLED->value],
                'route' => $tab(DevelopmentRoomTabsEnum::CANCELLED),
            ],
        ];
    }

    public function htmlResponse(Production $production, ActionRequest $request): Response
    {
        $parameters = $request->route()->originalParameters();
        $tables     = [
            DevelopmentRoomTabsEnum::EXPERIMENTS->value => fn () => DevelopmentArtefactsResource::collection($this->artefacts($production, ArtefactStateEnum::IN_PROCESS->value, DevelopmentRoomTabsEnum::EXPERIMENTS->value)),
            DevelopmentRoomTabsEnum::TRIAL_RUNS->value  => fn () => TrialRunsResource::collection($this->trialRuns($production, DevelopmentRoomTabsEnum::TRIAL_RUNS->value)),
            DevelopmentRoomTabsEnum::LAUNCHED->value    => fn () => DevelopmentArtefactsResource::collection($this->artefacts($production, DevelopmentRoomTabsEnum::LAUNCHED->value, DevelopmentRoomTabsEnum::LAUNCHED->value)),
            DevelopmentRoomTabsEnum::CANCELLED->value   => fn () => DevelopmentArtefactsResource::collection($this->artefacts($production, ArtefactStateEnum::CANCELLED->value, DevelopmentRoomTabsEnum::CANCELLED->value)),
        ];

        $props = [
            'breadcrumbs' => $this->getBreadcrumbs($parameters),
            'title'       => __('Development'),
            'pageHead'    => [
                'model'   => __('Crafts'),
                'title'   => __('Development'),
                'icon'    => ['icon' => ['fal', 'fa-vial'], 'title' => __('Development')],
                'actions' => [
                    $this->canEdit ? [
                        'type'  => 'button',
                        'style' => 'create',
                        'label' => __('Experiment'),
                        'route' => [
                            'name'       => 'grp.org.productions.show.crafts.artefacts.create',
                            'parameters' => $parameters,
                        ],
                    ] : null,
                ],
            ],
            'stats'       => $this->getStats($production, $parameters),
            'tabs'        => [
                'current'    => $this->tab,
                'navigation' => DevelopmentRoomTabsEnum::navigation(),
            ],
            'routes'      => [
                'artefact'  => ['name' => 'grp.org.productions.show.crafts.artefacts.show', 'parameters' => $parameters],
                'job_order' => ['name' => 'grp.org.productions.show.operations.job-orders.show', 'parameters' => $parameters],
            ],
            'actions'     => $this->canEdit ? [
                'trial_run_route'       => ['name' => 'grp.models.production.trial_run.store', 'parameters' => ['production' => $production->id]],
                'launch_route'          => ['name' => 'grp.models.production.artefacts.launch', 'parameters' => ['production' => $production->id]],
                'cancel_route'          => ['name' => 'grp.models.production.artefacts.cancel_development', 'parameters' => ['production' => $production->id]],
                'artisans'              => Employee::where('organisation_id', $production->organisation_id)
                    ->where('state', EmployeeStateEnum::WORKING)
                    ->orderBy('contact_name')
                    ->get(['id', 'contact_name'])
                    ->map(fn (Employee $employee) => ['id' => $employee->id, 'name' => $employee->contact_name])
                    ->all(),
            ] : null,
        ];

        foreach ($tables as $key => $table) {
            $props[$key] = $this->tab === $key ? $table : Inertia::optional($table);
        }

        $response = Inertia::render('Org/Production/DevelopmentRoom', $props);
        foreach ([DevelopmentRoomTabsEnum::EXPERIMENTS, DevelopmentRoomTabsEnum::LAUNCHED, DevelopmentRoomTabsEnum::CANCELLED] as $tab) {
            $response->table($this->artefactsTableStructure($tab->value));
        }

        return $response->table($this->trialRunsTableStructure(DevelopmentRoomTabsEnum::TRIAL_RUNS->value));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowCraftsDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.crafts.development',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Development'),
                        'icon'  => 'fal fa-vial',
                    ],
                ],
            ]
        );
    }
}
