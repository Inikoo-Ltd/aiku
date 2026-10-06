<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 10:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder\UI;

use App\Actions\Production\JobOrderItem\GetOpenJobOrderItemsOffBatch;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Actions\OrgAction;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Actions\Production\JobOrderItem\GetJobOrderItemMissingMixes;
use App\Models\Production\JobOrderItem;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use Illuminate\Support\Collection;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use Lorisleiva\Actions\ActionRequest;

class ShowJobOrder extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $jobOrder      = $request->route('jobOrder');
        $isOwnJobOrder = $jobOrder instanceof JobOrder && $jobOrder->employee_id
            && $jobOrder->employee_id == GetUserCurrentEmployee::run($request->user(), $this->organisation->id)?->id;

        $this->canEdit = $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]) || ($isOwnJobOrder && $request->user()->authTo("productions_operations.{$this->production->id}.prepare"));

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
        $warehouse = $this->organisation->warehouses()->first();

        $isOpen          = in_array($jobOrder->state, JobOrderStateEnum::open());
        $currencySymbol  = $this->organisation->currency->symbol;
        $allItems        = $jobOrder->jobOrderItems()
            ->with(['artefact.orgStock', 'tasks.manufactureTask', 'tasks.sessions', 'employee'])
            ->orderBy('id')
            ->get();
        $subJobsByLine   = $allItems->groupBy(fn (JobOrderItem $item) => $item->split_from_id ?? $item->id);

        $items = $allItems->whereNull('split_from_id')
            ->map(function (JobOrderItem $item) use ($jobOrder, $subJobsByLine, $isOpen, $currencySymbol) {
                $subJobs  = $subJobsByLine->get($item->id);
                $isSplit  = $subJobs->count() > 1 || $item->employee_id;
                $received = $subJobs->sum('quantity_received') > 0;

                return [
                    'id'                 => $item->id,
                    'artefact_code'      => $item->artefact->code,
                    'artefact_name'      => $item->artefact->name,
                    'quantity'           => (int)$subJobs->sum('quantity'),
                    'demand_skos'        => data_get($item->data, 'demand_skos'),
                    'suggested_quantity' => !$isSplit && $item->artefact->recommended_batch_size && $item->quantity % $item->artefact->recommended_batch_size
                        ? GetOpenJobOrderItemsOffBatch::make()->getSuggestedQuantity($item)
                        : null,
                    'update_route'       => $this->canEdit && $isOpen && !$isSplit && !$received ? [
                        'name'       => 'grp.models.job-order-item.update',
                        'parameters' => ['jobOrderItem' => $item->id],
                    ] : null,
                    'split_route'        => $this->canEdit && $isOpen && !$received ? [
                        'name'       => 'grp.models.job-order-item.split',
                        'parameters' => ['jobOrderItem' => $item->id],
                    ] : null,
                    'produced_quantity'  => (float)$subJobs->sum(fn (JobOrderItem $subJob) => $subJob->tasks->last()->quantity_made ?? 0),
                    'waiting_for'        => GetJobOrderItemMissingMixes::run($item),
                    'tasks'              => $this->lineTasks($subJobs),
                    'sub_jobs'           => $isSplit ? $subJobs->values()->map(fn (JobOrderItem $subJob, int $index) => $this->subJob($jobOrder, $subJob, $index, $currencySymbol))->all() : [],
                ];
            })
            ->values();

        $artefactOptions = Artefact::where('production_id', $this->production->id)
            ->whereNot('state', ArtefactStateEnum::DORMANT)
            ->withCount('manufactureTasks')
            ->orderBy('code')
            ->get()
            ->map(fn (Artefact $artefact) => [
                'id'         => $artefact->id,
                'code'       => $artefact->code,
                'name'       => $artefact->name,
                'has_recipe' => $artefact->manufacture_tasks_count > 0,
                'recommended_batch_size' => $artefact->recommended_batch_size,
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
                    'employee_id'  => $jobOrder->employee_id,
                    'artisan'      => $jobOrder->employee?->contact_name,
                ],
                'artisan_options' => $this->canEdit ? Employee::where('organisation_id', $this->organisation->id)
                    ->where('state', EmployeeStateEnum::WORKING)
                    ->orderBy('contact_name')
                    ->get(['id', 'contact_name'])
                    ->map(fn (Employee $employee) => ['id' => $employee->id, 'name' => $employee->contact_name])
                    ->all() : [],
                'update_route' => $this->canEdit ? [
                    'name'       => 'grp.models.job-order.update',
                    'parameters' => ['jobOrder' => $jobOrder->id],
                ] : null,
                'items'            => $items,
                'artefact_options' => $this->canEdit ? $artefactOptions : [],
                'add_item_route'   => $this->canEdit ? [
                    'name'       => 'grp.models.job-order.item.store',
                    'parameters' => ['jobOrder' => $jobOrder->id],
                ] : null,
                'confirm_route' => $this->canEdit && $jobOrder->state == JobOrderStateEnum::IN_PROCESS ? [
                    'name'       => 'grp.models.job-order.confirm',
                    'parameters' => ['jobOrder' => $jobOrder->id],
                ] : null,
                'receive_route' => $this->canEdit && $jobOrder->state == JobOrderStateEnum::CONFIRMED && $warehouse ? [
                    'name'       => 'grp.models.job-order.receive',
                    'parameters' => ['jobOrder' => $jobOrder->id],
                ] : null,
                'locations_fetch_route' => $warehouse ? [
                    'name'       => 'grp.org.warehouses.show.infrastructure.locations.index',
                    'parameters' => ['organisation' => $this->organisation->slug, 'warehouse' => $warehouse->slug],
                ] : null,
            ]
        );
    }

    /**
     * The recipe steps of a line, summed over all its sub-jobs.
     *
     * @param  Collection<int, JobOrderItem>  $subJobs
     */
    private function lineTasks(Collection $subJobs): array
    {
        return $subJobs->flatMap(fn (JobOrderItem $subJob) => $subJob->tasks)
            ->groupBy('manufacture_task_id')
            ->map(function (Collection $tasks) {
                /** @var JobOrderItemTask $task */
                $task  = $tasks->first();
                $state = JobOrderItemTaskStateEnum::TODO;
                if ($tasks->every(fn (JobOrderItemTask $task) => $task->state == JobOrderItemTaskStateEnum::DONE)) {
                    $state = JobOrderItemTaskStateEnum::DONE;
                } elseif ($tasks->contains(fn (JobOrderItemTask $task) => $task->state != JobOrderItemTaskStateEnum::TODO)) {
                    $state = JobOrderItemTaskStateEnum::IN_PROGRESS;
                }

                return [
                    'id'                => $task->id,
                    'position'          => $task->position,
                    'task_name'         => $task->manufactureTask->name,
                    'state'             => $state,
                    'quantity_required' => (float)$tasks->sum('quantity_required'),
                    'quantity_made'     => (float)$tasks->sum('quantity_made'),
                    'quantity_rejected' => (float)$tasks->sum('quantity_rejected'),
                ];
            })
            ->sortBy('position')
            ->values()
            ->all();
    }

    private function subJob(JobOrder $jobOrder, JobOrderItem $subJob, int $index, string $currencySymbol): array
    {
        $lastTask       = $subJob->tasks->last();
        $closedSessions = $subJob->tasks->flatMap(fn (JobOrderItemTask $task) => $task->sessions)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED);

        $state = 'assigned';
        if ($lastTask && $lastTask->state == JobOrderItemTaskStateEnum::DONE) {
            $state = 'complete';
        } elseif ($subJob->tasks->contains(fn (JobOrderItemTask $task) => $task->state != JobOrderItemTaskStateEnum::TODO)) {
            $state = 'in_progress';
        }

        return [
            'id'              => $subJob->id,
            'reference'       => $jobOrder->reference.'-'.chr(65 + $index),
            'employee_id'     => $subJob->employee_id,
            'artisan'         => $subJob->employee?->contact_name,
            'quantity'        => (int)$subJob->quantity,
            'quantity_made'   => (float)($lastTask->quantity_made ?? 0),
            'quantity_target' => (float)($lastTask->quantity_required ?? $subJob->quantity),
            'state'           => $state,
            'seconds'         => (int)round($closedSessions->sum(fn (ManufactureTaskSession $session) => $session->paidHours()) * 3600),
            'reward'          => $currencySymbol.number_format((float)$closedSessions->sum('pay'), 2),
            'can_remove'      => $subJob->tasks->every(fn (JobOrderItemTask $task) => $task->sessions->isEmpty()),
        ];
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
