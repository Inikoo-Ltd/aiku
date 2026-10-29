<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 10:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder\UI;

use App\Actions\Production\JobOrder\CancelJobOrder;
use App\Actions\Production\JobOrder\HoldJobOrder;
use App\Actions\Production\JobOrderItem\GetOpenJobOrderItemsOffBatch;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowArtisansDashboard;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
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
    private bool $canHold = false;
    private bool $canSeePay = false;

    public function authorize(ActionRequest $request): bool
    {
        $jobOrder      = $request->route('jobOrder');
        $isOwnJobOrder = $jobOrder instanceof JobOrder && $jobOrder->employee_id
            && $jobOrder->employee_id == GetUserCurrentEmployee::run($request->user(), $this->organisation->id)?->id;

        $this->canSeePay = ShowArtisansDashboard::canSeeArtisanPay($request->user(), $this->production);

        $this->canHold = $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);

        $isTrialRunOfDevelopmentTeam = $jobOrder instanceof JobOrder && $jobOrder->isTrialRun()
            && $request->user()->authTo("productions_rd.{$this->production->id}.edit");

        $this->canEdit = $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]) || $isTrialRunOfDevelopmentTeam || ($isOwnJobOrder && $request->user()->authTo("productions_operations.{$this->production->id}.prepare"));

        return $isTrialRunOfDevelopmentTeam || $request->user()->authTo([
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
            ->with(['artefact.orgStock', 'artefact.manufactureTasks', 'tasks.manufactureTask.artisans', 'tasks.sessions.user', 'tasks.sessionShares.session.user', 'employee', 'tasks.employee'])
            ->orderBy('id')
            ->get();
        $allItems->each(function (JobOrderItem $item) use ($jobOrder) {
            $item->setRelation('jobOrder', $jobOrder);
            $item->tasks->each(fn (JobOrderItemTask $task) => $task->setRelation('jobOrderItem', $item)->setRelation('jobOrder', $jobOrder));
        });
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
                    'produced_quantity'  => (float)$subJobs->sum(fn (JobOrderItem $subJob) => $subJob->finalTask()?->quantity_made ?? 0),
                    'waiting_for'        => GetJobOrderItemMissingMixes::run($item),
                    'tasks'              => $this->lineTasks($subJobs, $jobOrder, $this->canEdit),
                    'sub_jobs'           => $isSplit ? $subJobs->values()->map(fn (JobOrderItem $subJob, int $index) => $this->subJob($jobOrder, $subJob, $index, $currencySymbol))->all() : [],
                ];
            })
            ->values();

        $artefactOptions = Artefact::where('production_id', $this->production->id)
            ->whereIn('state', $jobOrder->isTrialRun() ? [ArtefactStateEnum::IN_PROCESS] : ArtefactStateEnum::launched())
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
                    'overproductions' => $jobOrder->data['overproductions'] ?? [],
                    'corrections'     => $jobOrder->data['corrections'] ?? [],
                    'non_productive'  => $this->nonProductive($jobOrder, $currencySymbol),
                    'hold'            => $this->holdWithLabel($jobOrder->data['hold'] ?? null),
                    'holds'           => array_map(fn (array $hold) => $this->holdWithLabel($hold), $jobOrder->data['holds'] ?? []),
                    'cancellation'    => $this->cancellationWithLabel($jobOrder->data['cancellation'] ?? null),
                ],
                'can_see_pay'       => $this->canSeePay,
                'hold_route'        => $this->canHold && ($isOpen || isset($jobOrder->data['hold'])) ? [
                    'name'       => 'grp.models.job-order.hold',
                    'parameters' => ['jobOrder' => $jobOrder->id],
                ] : null,
                'cancel_route'      => $this->canHold && $isOpen && !isset($jobOrder->data['cancellation']) ? [
                    'name'       => 'grp.models.job-order.cancel',
                    'parameters' => ['jobOrder' => $jobOrder->id],
                ] : null,
                'cancel_kind_options' => collect(CancelJobOrder::kindLabels())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
                'hold_kind_options' => collect(HoldJobOrder::kindLabels())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
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
                'pdf_route'        => [
                    'name'       => 'grp.org.productions.show.operations.job-orders.pdf',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'artefact_options' => $this->canEdit ? $artefactOptions : [],
                'add_item_route'   => $this->canEdit ? [
                    'name'       => 'grp.models.job-order.item.store',
                    'parameters' => ['jobOrder' => $jobOrder->id],
                ] : null,
                'confirm_route' => $this->canEdit && $jobOrder->state == JobOrderStateEnum::IN_PROCESS ? [
                    'name'       => 'grp.models.job-order.confirm',
                    'parameters' => ['jobOrder' => $jobOrder->id],
                ] : null,
                'is_trial_run'  => $jobOrder->isTrialRun(),
                'receive_route' => $this->canEdit && $jobOrder->state == JobOrderStateEnum::CONFIRMED && ($warehouse || $jobOrder->isTrialRun()) ? [
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

    private function cancellationWithLabel(?array $cancellation): ?array
    {
        return $cancellation ? array_merge($cancellation, ['kind_label' => CancelJobOrder::kindLabels()[$cancellation['kind'] ?? 'other'] ?? null]) : null;
    }

    private function holdWithLabel(?array $hold): ?array
    {
        return $hold ? array_merge($hold, ['kind_label' => HoldJobOrder::kindLabels()[$hold['kind'] ?? 'other'] ?? null]) : null;
    }

    /**
     * The recipe steps of a line, summed over all its sub-jobs.
     *
     * @param  Collection<int, JobOrderItem>  $subJobs
     */
    private function lineTasks(Collection $subJobs, JobOrder $jobOrder, bool $canAssign): array
    {
        $subJobReferences = $subJobs->values()->mapWithKeys(fn (JobOrderItem $subJob, int $index) => [$subJob->id => $jobOrder->reference.'-'.chr(65 + $index)]);

        return $subJobs->flatMap(fn (JobOrderItem $subJob) => $subJob->tasks)
            ->groupBy('manufacture_task_id')
            ->map(function (Collection $tasks) use ($subJobReferences, $subJobs, $canAssign) {
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
                    'unit'              => $task->unit(),
                    'mix_batch_kg'      => $task->mixBatchKg(),
                    'combined_with'     => $this->combinedWith($tasks),
                    'owners'            => $tasks->map(fn (JobOrderItemTask $stepTask) => [
                        'id'          => $stepTask->id,
                        'sub_job'     => $subJobs->count() > 1 ? $subJobReferences[$stepTask->job_order_item_id] : null,
                        'owner_id'    => $stepTask->assigneeId(),
                        'owner'       => $stepTask->assignee()?->contact_name,
                        'only_for'    => $stepTask->manufactureTask->artisans->pluck('id')->all(),
                        'can_assign'  => $canAssign && $stepTask->state != JobOrderItemTaskStateEnum::DONE,
                    ])->values()->all(),
                ];
            })
            ->sortBy('position')
            ->values()
            ->all();
    }

    /**
     * The other lines this step is made in one batch with, e.g. "SLHCS-48 (JO-0071)".
     *
     * @param Collection<int, JobOrderItemTask> $tasks
     */
    private function combinedWith(Collection $tasks): ?string
    {
        $taskIds = $tasks->pluck('id');
        $others  = $tasks->whereNotNull('combined_task_id')
            ->flatMap(fn (JobOrderItemTask $task) => $task->combinedGroup())
            ->reject(fn (JobOrderItemTask $member) => $taskIds->contains($member->id))
            ->unique('id')
            ->map(fn (JobOrderItemTask $member) => $member->jobOrderItem->artefact->code.' ('.$member->jobOrder->reference.')');

        return $others->isEmpty() ? null : $others->implode(', ');
    }

    /**
     * Preparation and clean-down booked to this job order, so the batch shows its full labour cost.
     *
     * @return array<int, array{activity: string, hours: float, pay: string|null}>
     */
    private function nonProductive(JobOrder $jobOrder, string $currencySymbol): array
    {
        return ManufactureTaskSession::where('job_order_id', $jobOrder->id)
            ->whereNull('job_order_item_task_id')
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->get()
            ->groupBy(fn (ManufactureTaskSession $session) => $session->activity_type->value)
            ->map(fn (Collection $sessions) => [
                'activity' => $sessions->first()->activityLabel(),
                'hours'    => round($sessions->sum(fn (ManufactureTaskSession $session) => $session->paidHours()), 2),
                'pay'      => !$this->canSeePay || $sessions->whereNotNull('pay')->isEmpty() ? null : $currencySymbol.number_format((float) $sessions->sum('pay'), 2),
            ])
            ->values()
            ->all();
    }

    private function subJob(JobOrder $jobOrder, JobOrderItem $subJob, int $index, string $currencySymbol): array
    {
        $lastTask       = $subJob->finalTask();
        $closedWork = $subJob->tasks->flatMap(fn (JobOrderItemTask $task) => $task->closedWork());

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
            'seconds'         => (int)round($closedWork->sum('hours') * 3600),
            'reward'          => $this->canSeePay ? $currencySymbol.number_format((float)$closedWork->sum('pay'), 2) : null,
            'can_remove'      => $subJob->tasks->every(fn (JobOrderItemTask $task) => $task->sessions->isEmpty() && $task->sessionShares->isEmpty()),
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
