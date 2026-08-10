<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 10:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowOperationsDashboard;
use App\Http\Resources\Production\JobOrdersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Production\JobOrder;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexJobOrders extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
        ]);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production);
    }

    public function handle(Production $production, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('job_orders.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(JobOrder::class)
            ->where('job_orders.production_id', $production->id)
            ->leftJoin('job_order_item_tasks', 'job_order_item_tasks.job_order_id', 'job_orders.id')
            ->select('job_orders.id', 'job_orders.slug', 'job_orders.reference', 'job_orders.state', 'job_orders.date')
            ->selectRaw('count(job_order_item_tasks.id) as number_tasks')
            ->selectRaw("count(job_order_item_tasks.id) filter (where job_order_item_tasks.state = 'done') as number_tasks_done")
            ->groupBy('job_orders.id')
            ->defaultSort('-date')
            ->allowedSorts(['reference', 'date', 'state'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Production $production, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($production, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('No job orders yet'),
                        'description' => $this->canEdit ? __('Create your first job order to feed the manufacture floor') : null,
                        'count'       => $production->stats->number_job_orders,
                    ]
                )
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'tasks', label: __('Tasks done'), canBeHidden: false)
                ->defaultSort('-date');
        };
    }

    public function jsonResponse(LengthAwarePaginator $jobOrders): AnonymousResourceCollection
    {
        return JobOrdersResource::collection($jobOrders);
    }

    public function htmlResponse(LengthAwarePaginator $jobOrders, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/JobOrders',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Job orders'),
                'pageHead'    => [
                    'title'   => __('Job orders'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-sort-shapes-down'],
                        'title' => __('Job orders'),
                    ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New job order'),
                            'label'   => __('job order'),
                            'route'   => [
                                'method'     => 'post',
                                'name'       => 'grp.models.production.job-order.store',
                                'parameters' => ['production' => $this->production->id],
                            ],
                        ] : null,
                    ],
                ],
                'data'        => JobOrdersResource::collection($jobOrders),
            ]
        )->table($this->tableStructure($this->production));
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
                            'name'       => 'grp.org.productions.show.operations.job-orders.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Job orders'),
                    ],
                    'suffix' => $suffix,
                ],
            ]
        );
    }
}
