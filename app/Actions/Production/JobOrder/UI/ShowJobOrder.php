<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 10:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder\UI;

use App\Actions\OrgAction;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowJobOrder extends OrgAction
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

    public function asController(Organisation $organisation, Production $production, JobOrder $jobOrder, ActionRequest $request): JobOrder
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($jobOrder);
    }

    public function handle(JobOrder $jobOrder): JobOrder
    {
        return $jobOrder;
    }

    public function htmlResponse(JobOrder $jobOrder, ActionRequest $request): Response
    {
        $items = $jobOrder->jobOrderItems()
            ->with(['artefact', 'tasks.manufactureTask'])
            ->get()
            ->map(fn (JobOrderItem $item) => [
                'id'            => $item->id,
                'artefact_code' => $item->artefact->code,
                'artefact_name' => $item->artefact->name,
                'quantity'      => (int)$item->quantity,
                'tasks'         => $item->tasks->map(fn (JobOrderItemTask $task) => [
                    'id'                => $task->id,
                    'position'          => $task->position,
                    'task_name'         => $task->manufactureTask->name,
                    'state'             => $task->state,
                    'quantity_required' => (float)$task->quantity_required,
                    'quantity_made'     => (float)$task->quantity_made,
                    'quantity_rejected' => (float)$task->quantity_rejected,
                ])->values(),
            ]);

        $artefactOptions = Artefact::where('production_id', $this->production->id)
            ->withCount('manufactureTasks')
            ->orderBy('code')
            ->get()
            ->map(fn (Artefact $artefact) => [
                'id'         => $artefact->id,
                'code'       => $artefact->code,
                'name'       => $artefact->name,
                'has_recipe' => $artefact->manufacture_tasks_count > 0,
            ])->values();

        return Inertia::render(
            'Org/Production/JobOrder',
            [
                'breadcrumbs' => $this->getBreadcrumbs($jobOrder, $request->route()->originalParameters()),
                'title'       => __('Job order').' '.$jobOrder->reference,
                'pageHead'    => [
                    'model' => __('Job order'),
                    'title' => $jobOrder->reference,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-sort-shapes-down'],
                        'title' => __('Job order'),
                    ],
                ],
                'job_order'   => [
                    'id'          => $jobOrder->id,
                    'reference'   => $jobOrder->reference,
                    'state'       => $jobOrder->state,
                    'state_label' => JobOrderStateEnum::labels()[$jobOrder->state->value] ?? $jobOrder->state->value,
                    'date'        => $jobOrder->date,
                    'created_at'  => $jobOrder->created_at,
                    'public_notes' => $jobOrder->public_notes,
                ],
                'items'            => $items,
                'artefact_options' => $this->canEdit ? $artefactOptions : [],
                'add_item_route'   => $this->canEdit ? [
                    'name'       => 'grp.models.job-order.item.store',
                    'parameters' => ['jobOrder' => $jobOrder->id],
                ] : null,
            ]
        );
    }

    public function getBreadcrumbs(JobOrder $jobOrder, array $routeParameters, $suffix = null): array
    {
        return array_merge(
            (new IndexJobOrders())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.operations.job-orders.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $jobOrder->reference,
                    ],
                    'suffix' => $suffix,
                ],
            ]
        );
    }
}
