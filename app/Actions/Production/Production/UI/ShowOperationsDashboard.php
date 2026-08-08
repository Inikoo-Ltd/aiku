<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 20:55:56 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Production\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Enums\UI\Production\ProductionTabsEnum;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Production\ProductionResource;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOperationsDashboard extends OrgAction
{
    use WithActionButtons;

    public function handle(Production $production): Production
    {
        return $production;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo('org-supervisor.'.$this->organisation->id);
        $this->canDelete = $request->user()->authTo('org-supervisor.'.$this->organisation->id);


        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_rd.{$this->production->id}.view",
            "productions_procurement.{$this->production->id}.view",

        ]);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request)->withTab(ProductionTabsEnum::values());

        return $this->handle($production);
    }


    public function htmlResponse(Production $production, ActionRequest $request): Response
    {

        return Inertia::render(
            'Org/Production/OperationsDashboard',
            [
                'title'                            => __('Operations'),
                'breadcrumbs'                      => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'                       => [
                    'previous' => $this->getPrevious($production, $request),
                    'next'     => $this->getNext($production, $request),
                ],
                'pageHead'                         => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-fill-drip'],
                            'title' => __('Factory operations')
                        ],
                        'iconRight' => [
                            'icon'  => ['fal', 'fa-chart-network'],
                            'title' => __('Factory operations')
                        ],
                    'title'   => __('Operations'),
                    'actions' => [],


                ],
                'flatTreeMaps' => [
                    [
                        [
                            'name'  => __('Job orders'),
                            'icon'  => ['fal', 'fa-sort-shapes-down'],
                            'route' => [
                                'name'       => 'grp.org.productions.show.operations.job-orders.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                            'index' => [
                                'number' => $production->jobOrders()
                                    ->whereIn('state', [
                                        JobOrderStateEnum::IN_PROCESS,
                                        JobOrderStateEnum::SUBMITTED,
                                        JobOrderStateEnum::CONFIRMED,
                                    ])->count()
                            ],
                        ],
                        [
                            'name'      => __('Tasks in queue'),
                            'shortName' => __('queue'),
                            'icon'      => ['fal', 'fa-tasks'],
                            'route'     => [
                                'name'       => 'grp.org.productions.show.floor',
                                'parameters' => $request->route()->originalParameters()
                            ],
                            'index'     => [
                                'number' => JobOrderItemTask::where('production_id', $production->id)
                                    ->where('state', '!=', JobOrderItemTaskStateEnum::DONE)
                                    ->count()
                            ],
                        ],
                        [
                            'name'      => __('Working now'),
                            'shortName' => __('working'),
                            'icon'      => ['fal', 'fa-user-hard-hat'],
                            'route'     => [
                                'name'       => 'grp.org.productions.show.floor',
                                'parameters' => $request->route()->originalParameters()
                            ],
                            'index'     => [
                                'number' => ManufactureTaskSession::where('production_id', $production->id)
                                    ->where('state', ManufactureTaskSessionStateEnum::OPEN)
                                    ->count()
                            ],
                        ],
                        [
                            'name'      => __('Made today'),
                            'shortName' => __('today'),
                            'icon'      => ['fal', 'fa-cubes'],
                            'route'     => [
                                'name'       => 'grp.org.productions.show.floor',
                                'parameters' => $request->route()->originalParameters()
                            ],
                            'index'     => [
                                'number' => (int)ManufactureTaskSession::where('production_id', $production->id)
                                    ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
                                    ->whereDate('ended_at', now()->toDateString())
                                    ->sum('quantity_made')
                            ],
                        ],
                    ],
                ],
                'command_control' => [
                    'payroll_export_route' => [
                        'name'       => 'grp.org.productions.show.operations.payroll.export',
                        'parameters' => $request->route()->originalParameters()
                    ],
                    'floor_route' => [
                        'name'       => 'grp.org.productions.show.floor',
                        'parameters' => $request->route()->originalParameters()
                    ],
                    'open_session' => ($openSession = ManufactureTaskSession::where('user_id', $request->user()->id)
                        ->where('state', ManufactureTaskSessionStateEnum::OPEN)
                        ->with(['jobOrderItemTask.jobOrderItem.artefact', 'jobOrderItemTask.jobOrder', 'manufactureTask'])
                        ->first()) ? [
                            'id'         => $openSession->id,
                            'started_at' => $openSession->started_at,
                            'task'       => [
                                'task_name'           => $openSession->manufactureTask->name,
                                'artefact_code'       => $openSession->jobOrderItemTask->jobOrderItem->artefact->code,
                                'artefact_name'       => $openSession->jobOrderItemTask->jobOrderItem->artefact->name,
                                'job_order_reference' => $openSession->jobOrderItemTask->jobOrder->reference,
                                'quantity_made'       => (float)$openSession->jobOrderItemTask->quantity_made,
                                'quantity_required'   => (float)$openSession->jobOrderItemTask->quantity_required,
                            ],
                            'close_route' => [
                                'name'       => 'grp.models.manufacture-task-session.close',
                                'parameters' => ['manufactureTaskSession' => $openSession->id],
                            ],
                        ] : null,
                    'working_now' => ManufactureTaskSession::where('manufacture_task_sessions.production_id', $production->id)
                        ->where('manufacture_task_sessions.state', ManufactureTaskSessionStateEnum::OPEN)
                        ->with(['user', 'manufactureTask', 'jobOrderItemTask.jobOrderItem.artefact', 'jobOrderItemTask.jobOrder'])
                        ->orderBy('started_at')
                        ->get()
                        ->map(fn (ManufactureTaskSession $session) => [
                            'id'                  => $session->id,
                            'worker'              => $session->user->contact_name ?: $session->user->username,
                            'task_name'           => $session->manufactureTask->name,
                            'artefact_code'       => $session->jobOrderItemTask->jobOrderItem->artefact->code,
                            'job_order_reference' => $session->jobOrderItemTask->jobOrder->reference,
                            'started_at'          => $session->started_at,
                            'quantity_made'       => (float)$session->jobOrderItemTask->quantity_made,
                            'quantity_required'   => (float)$session->jobOrderItemTask->quantity_required,
                        ]),
                    'queue'       => JobOrderItemTask::where('job_order_item_tasks.production_id', $production->id)
                        ->where('job_order_item_tasks.state', '!=', JobOrderItemTaskStateEnum::DONE)
                        ->with(['jobOrderItem.artefact', 'jobOrder', 'manufactureTask'])
                        ->join('job_orders', 'job_orders.id', '=', 'job_order_item_tasks.job_order_id')
                        ->orderBy('job_orders.date')
                        ->orderBy('job_order_item_tasks.position')
                        ->select('job_order_item_tasks.*')
                        ->limit(20)
                        ->get()
                        ->map(fn (JobOrderItemTask $task) => [
                            'id'                  => $task->id,
                            'state'               => $task->state,
                            'task_name'           => $task->manufactureTask->name,
                            'artefact_code'       => $task->jobOrderItem->artefact->code,
                            'artefact_name'       => $task->jobOrderItem->artefact->name,
                            'job_order_reference' => $task->jobOrder->reference,
                            'quantity_made'       => (float)$task->quantity_made,
                            'quantity_required'   => (float)$task->quantity_required,
                            'start_route'         => [
                                'name'       => 'grp.models.job-order-item-task.session.store',
                                'parameters' => ['jobOrderItemTask' => $task->id],
                            ],
                        ]),
                ],
                'tabs'                             => [

                    'current'    => $this->tab,
                    'navigation' => ProductionTabsEnum::navigation(),
                ],

                ProductionTabsEnum::SHOWCASE->value => $this->tab == ProductionTabsEnum::SHOWCASE->value ?
                    fn () => GetProductionShowcase::run($production)
                    : Inertia::optional(fn () => GetProductionShowcase::run($production)),





                ProductionTabsEnum::HISTORY->value => $this->tab == ProductionTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($production))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($production)))

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: ProductionTabsEnum::HISTORY->value));
    }


    public function jsonResponse(Production $production): ProductionResource
    {
        return new ProductionResource($production);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $production = Production::where('slug', $routeParameters['production'])->first();

        return array_merge(
            (new ShowOrganisationDashboard())->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.productions.index',
                                'parameters' => $routeParameters['organisation']
                            ],
                            'label' => __('Factories'),
                            'icon'  => 'fal fa-bars'
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.productions.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => $production?->code,
                            'icon'  => 'fal fa-bars'
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(Production $production, ActionRequest $request): ?array
    {
        $previous = Production::where('code', '<', $production->code)->where('organisation_id', $production->organisation_id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Production $production, ActionRequest $request): ?array
    {
        $next = Production::where('code', '>', $production->code)->where('organisation_id', $production->organisation_id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Production $production, string $routeName): ?array
    {
        if (!$production) {
            return null;
        }

        return match ($routeName) {
            'grp.org.productions.show.operations.dashboard' => [
                'label' => $production->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $this->organisation->slug,
                        'production'    => $production->slug
                    ]

                ]
            ]
        };
    }
}
