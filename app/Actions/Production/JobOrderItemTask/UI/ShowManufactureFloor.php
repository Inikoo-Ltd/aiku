<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItemTask\UI;

use App\Actions\OrgAction;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowManufactureFloor extends OrgAction
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
        ]);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production);
    }

    public function htmlResponse(Production $production, ActionRequest $request): Response
    {
        $user = $request->user();

        $openSession = ManufactureTaskSession::where('user_id', $user->id)
            ->where('state', ManufactureTaskSessionStateEnum::OPEN)
            ->with(['jobOrderItemTask.jobOrderItem.artefact', 'jobOrderItemTask.jobOrder', 'manufactureTask'])
            ->first();

        $tasks = JobOrderItemTask::where('job_order_item_tasks.production_id', $production->id)
            ->where('job_order_item_tasks.state', '!=', JobOrderItemTaskStateEnum::DONE)
            ->with(['jobOrderItem.artefact', 'jobOrder', 'manufactureTask'])
            ->join('job_orders', 'job_orders.id', '=', 'job_order_item_tasks.job_order_id')
            ->orderBy('job_orders.date')
            ->orderBy('job_order_item_tasks.position')
            ->select('job_order_item_tasks.*')
            ->get()
            ->map(fn (JobOrderItemTask $task) => $this->serializeTask($task));

        $todayTotals = ManufactureTaskSession::where('user_id', $user->id)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->whereDate('ended_at', now()->toDateString())
            ->selectRaw('count(*) as sessions, coalesce(sum(quantity_made),0) as quantity_made, coalesce(sum(quantity_made * task_work_cost),0) as earned')
            ->first();

        return Inertia::render(
            'Org/Production/ManufactureFloor',
            [
                'title'       => __('Manufacture floor'),
                'breadcrumbs' => [],
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-industry'],
                        'title' => __('Manufacture floor'),
                    ],
                    'title' => __('My tasks'),
                ],
                'open_session' => $openSession ? [
                    'id'         => $openSession->id,
                    'started_at' => $openSession->started_at,
                    'task'       => $this->serializeTask($openSession->jobOrderItemTask),
                    'close_route' => [
                        'name'       => 'grp.models.manufacture-task-session.close',
                        'parameters' => ['manufactureTaskSession' => $openSession->id],
                        'method'     => 'patch',
                    ],
                ] : null,
                'tasks'        => $tasks,
                'today'        => [
                    'sessions'      => (int)$todayTotals->sessions,
                    'quantity_made' => (float)$todayTotals->quantity_made,
                    'earned'        => (float)$todayTotals->earned,
                ],
            ]
        );
    }

    protected function serializeTask(JobOrderItemTask $task): array
    {
        return [
            'id'                  => $task->id,
            'state'               => $task->state,
            'position'            => $task->position,
            'task_code'           => $task->manufactureTask->code,
            'task_name'           => $task->manufactureTask->name,
            'artefact_code'       => $task->jobOrderItem->artefact->code,
            'artefact_name'       => $task->jobOrderItem->artefact->name,
            'job_order_reference' => $task->jobOrder->reference,
            'quantity_required'   => (float)$task->quantity_required,
            'quantity_made'       => (float)$task->quantity_made,
            'start_route'         => [
                'name'       => 'grp.models.job-order-item-task.session.store',
                'parameters' => ['jobOrderItemTask' => $task->id],
                'method'     => 'post',
            ],
        ];
    }
}
