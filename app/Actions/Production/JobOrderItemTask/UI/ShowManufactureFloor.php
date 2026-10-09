<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 22:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItemTask\UI;

use App\Actions\Production\JobOrderItem\GetJobOrderItemMissingMixes;
use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowProduction;
use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufacturePayBand;
use App\Actions\Production\ManufactureBreak\StartManufactureBreak;
use App\Models\Production\ManufactureBreak;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowManufactureFloor extends OrgAction
{
    private ?Employee $employee = null;

    /** @var array<int, string[]> */
    private array $missingMixes = [];

    /** @var array<int, Collection<int, JobOrderItemTask>> combined steps by the id of the step that stands for them */
    private array $combinedGroups = [];

    private bool $canCombine = false;

    public function handle(Production $production): Production
    {
        return $production;
    }

    public static function canPickOpenJobs(User $user, Production $production): bool
    {
        return $user->authTo([
            'org-supervisor.'.$production->organisation_id,
            'productions-view.'.$production->organisation_id,
            "productions_operations.$production->id.edit",
            "productions_operations.$production->id.orchestrate",
            "productions_operations.$production->id.prepare",
        ]);
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
        $user           = $request->user();
        $this->employee = GetUserCurrentEmployee::run($user, $production->organisation_id);

        $openBreak = ManufactureBreak::where('user_id', $user->id)->open()->first();

        $openSession = ManufactureTaskSession::where('user_id', $user->id)
            ->where('state', ManufactureTaskSessionStateEnum::OPEN)
            ->with(['jobOrderItemTask.jobOrderItem.artefact', 'jobOrderItemTask.jobOrderItem.employee', 'jobOrderItemTask.jobOrder.employee', 'manufactureTask', 'jobOrder'])
            ->first();

        $workingOnBy = ManufactureTaskSession::where('production_id', $production->id)
            ->where('state', ManufactureTaskSessionStateEnum::OPEN)
            ->where('user_id', '!=', $user->id)
            ->with('user')
            ->get()
            ->groupBy('job_order_item_task_id')
            ->map(fn ($sessions) => $sessions->map(fn (ManufactureTaskSession $session) => $session->user->contact_name ?: $session->user->username)->values()->all());

        $canPickOpenJobs  = $this->canPickOpenJobs($user, $production);
        $this->canCombine = $user->authTo([
            'org-supervisor.'.$production->organisation_id,
            "productions_operations.$production->id.orchestrate",
        ]);

        $openTasks = JobOrderItemTask::where('job_order_item_tasks.production_id', $production->id)
            ->where('job_order_item_tasks.state', '!=', JobOrderItemTaskStateEnum::DONE)
            ->with(['jobOrderItem.artefact.manufactureTasks', 'jobOrderItem.employee', 'jobOrder.employee', 'manufactureTask'])
            ->join('job_orders', 'job_orders.id', '=', 'job_order_item_tasks.job_order_id')
            ->join('job_order_items', 'job_order_items.id', '=', 'job_order_item_tasks.job_order_item_id')
            ->live()
            ->where(function ($query) {
                $query->where('job_orders.state', JobOrderStateEnum::CONFIRMED)
                    ->orWhere(function ($query) {
                        $query->where('job_orders.state', JobOrderStateEnum::IN_PROCESS)
                            ->whereRaw('coalesce(job_order_items.employee_id, job_orders.employee_id) = ?', [$this->employee?->id ?? 0]);
                    });
            })
            ->when(!$canPickOpenJobs, fn ($query) => $query->where(function ($query) {
                $query->whereRaw('coalesce(job_order_items.employee_id, job_orders.employee_id) = ?', [$this->employee?->id ?? 0])
                    ->orWhereIn('job_order_item_tasks.combined_task_id', $this->combinedTaskIdsOf($this->employee));
            }))
            ->orderBy('job_orders.date')
            ->orderBy('job_order_item_tasks.position')
            ->orderBy('job_order_item_tasks.id')
            ->select('job_order_item_tasks.*')
            ->get();

        $openTasksByJobOrderItem = $openTasks->groupBy('job_order_item_id');

        $openTasks = $this->collapseCombined($openTasks);

        $stepsByJobOrderItem = JobOrderItemTask::whereIn('job_order_item_id', $openTasksByJobOrderItem->keys())
            ->with([
                'manufactureTask',
                'sessions' => fn ($query) => $query->whereIn('state', [ManufactureTaskSessionStateEnum::OPEN, ManufactureTaskSessionStateEnum::CLOSED])->with('user'),
                'sessionShares.session.user',
            ])
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('job_order_item_id');

        $serializedSteps = [];

        $tasks = $openTasks
            ->map(function (JobOrderItemTask $task) use ($openTasksByJobOrderItem, $workingOnBy, $stepsByJobOrderItem, &$serializedSteps) {
                $members      = $this->combinedGroups[$task->id] ?? collect([$task]);
                $blockingStep = $members->map(fn (JobOrderItemTask $member) => match (true) {
                    $member->id == $task->id                         => $task->blockingStep($openTasksByJobOrderItem->get($task->job_order_item_id)),
                    $member->state == JobOrderItemTaskStateEnum::DONE => null,
                    default                                          => $member->blockingStep($member->jobOrderItem->tasks),
                })->filter()->first();
                $workingOn    = $members->flatMap(fn (JobOrderItemTask $member) => $workingOnBy->get($member->id, []))->unique()->values()->all();

                return $this->serializeTask($task) + [
                    'working_on_by'   => $workingOn,
                    'blocked_by_step' => $blockingStep?->manufactureTask->name,
                    'can_start'       => !$blockingStep && !$workingOn,
                    'steps'           => $serializedSteps[$task->job_order_item_id] ??= $this->serializeSteps($stepsByJobOrderItem->get($task->job_order_item_id), $task),
                ];
            })
            ->sortBy(fn (array $task) => !$task['can_start'] || count($task['waiting_for']))
            ->values();

        $finishedToday = ManufactureTaskSession::where('user_id', $user->id)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->whereDate('ended_at', now()->toDateString())
            ->with(['jobOrderItemTask.jobOrderItem.artefact', 'jobOrderItemTask.jobOrder', 'manufactureTask', 'jobOrder', 'shares.jobOrderItemTask.jobOrderItem.artefact'])
            ->orderByDesc('ended_at')
            ->get()
            ->map(fn (ManufactureTaskSession $session) => [
                'id'                  => $session->id,
                'ended_at'            => $session->ended_at,
                'seconds'             => (int) $session->started_at->diffInSeconds($session->ended_at),
                'is_non_productive'   => $session->isNonProductive(),
                'task_name'           => $session->isNonProductive() ? $session->activityLabel() : $session->manufactureTask->name,
                'artefact_code'       => $session->isNonProductive()
                    ? ($session->jobOrder?->reference ?? __('General'))
                    : $session->artefactCode(),
                'artefact_name'       => $session->jobOrderItemTask?->jobOrderItem->artefact->name,
                'job_order_reference' => $session->isNonProductive() ? $session->jobOrder?->reference : $session->jobOrderItemTask->jobOrder->reference,
                'quantity_made'       => (float) $session->quantity_made,
                'quantity_rejected'   => (float) $session->quantity_rejected,
            ]);

        $todayTotals = ManufactureTaskSession::where('user_id', $user->id)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->whereNotNull('job_order_item_task_id')
            ->whereDate('ended_at', now()->toDateString())
            ->selectRaw('count(*) as sessions, coalesce(sum(quantity_made),0) as quantity_made, coalesce(sum(quantity_made * task_work_cost),0) as earned')
            ->first();

        return Inertia::render(
            'Org/Production/ManufactureFloor',
            [
                'title'       => __('Manufacture floor'),
                'server_time' => now()->toIso8601ZuluString('millisecond'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-industry'],
                        'title' => __('Manufacture floor'),
                    ],
                    'title' => __('My tasks'),
                ],
                'production_id' => $this->production->id,
                'break_options' => StartManufactureBreak::ALLOWED_MINUTES,
                'break_route'   => [
                    'name'       => 'grp.models.production.break.store',
                    'parameters' => ['production' => $this->production->id],
                    'method'     => 'post',
                ],
                'open_break'    => $openBreak ? [
                    'id'              => $openBreak->id,
                    'planned_minutes' => $openBreak->planned_minutes,
                    'started_at'      => $openBreak->started_at,
                    'is_clocked_out'  => (bool) $openBreak->clock_out_clocking_id,
                    'end_route'       => [
                        'name'       => 'grp.models.manufacture-break.end',
                        'parameters' => ['manufactureBreak' => $openBreak->id],
                        'method'     => 'patch',
                    ],
                ] : null,
                'non_productive' => [
                    'route'      => [
                        'name'       => 'grp.models.production.non_productive_session.store',
                        'parameters' => ['production' => $this->production->id],
                        'method'     => 'post',
                    ],
                    'activities' => array_map(fn (ManufactureTaskSessionActivityTypeEnum $activity) => [
                        'value' => $activity->value,
                        'label' => ManufactureTaskSessionActivityTypeEnum::labels()[$activity->value],
                    ], ManufactureTaskSessionActivityTypeEnum::floorActivities()),
                    'job_orders' => $this->nonProductiveJobOrderOptions($production),
                ],
                'open_session' => $openSession?->isNonProductive() ? self::serializeNonProductiveSession($openSession) : ($openSession ? [
                    'id'         => $openSession->id,
                    'can_reject' => $canPickOpenJobs,
                    'started_at' => $openSession->started_at,
                    'task'       => $this->serializeTask($openSession->jobOrderItemTask, $openSession->is_combined ? $openSession->jobOrderItemTask->combinedGroup() : null),
                    'close_route' => [
                        'name'       => 'grp.models.manufacture-task-session.close',
                        'parameters' => ['manufactureTaskSession' => $openSession->id],
                        'method'     => 'patch',
                    ],
                    'band_feedback' => $this->bandFeedback($openSession),
                    'break_minutes' => (int) $openSession->break_minutes,
                ] : null),
                'artisan'      => $this->employee?->contact_name,
                'can_pick_open_jobs' => $canPickOpenJobs,
                'combine_route'      => $this->canCombine ? [
                    'name'       => 'grp.models.production.combined_task.store',
                    'parameters' => ['production' => $this->production->id],
                    'method'     => 'post',
                ] : null,
                'tasks'        => $tasks,
                'finished_today' => $finishedToday,
                'today'        => [
                    'sessions'      => (int)$todayTotals->sessions,
                    'quantity_made' => (float)$todayTotals->quantity_made,
                    'earned'        => (float)$todayTotals->earned,
                ],
            ]
        );
    }

    /**
     * The working card's shape, without a step or quantity: the card shows the activity and a FINISH button.
     */
    public static function serializeNonProductiveSession(ManufactureTaskSession $session): array
    {
        return [
            'id'            => $session->id,
            'started_at'    => $session->started_at,
            'activity'      => [
                'type'  => $session->activity_type->value,
                'label' => $session->activityLabel(),
            ],
            'task'          => [
                'task_name'           => $session->activityLabel(),
                'artefact_code'       => null,
                'artefact_name'       => null,
                'job_order_reference' => $session->jobOrder?->reference,
                'job_order_slug'      => $session->jobOrder?->slug,
                'quantity_made'       => 0,
                'quantity_required'   => 0,
            ],
            'close_route'   => [
                'name'       => 'grp.models.manufacture-task-session.close',
                'parameters' => ['manufactureTaskSession' => $session->id],
                'method'     => 'patch',
            ],
            'band_feedback' => null,
            'break_minutes' => (int) $session->break_minutes,
        ];
    }

    /**
     * Job orders on the floor, plus any worked in the last week, so a clean-down after the batch is finished or received can still be booked to it.
     *
     * @return array<int, array{id: int, label: string}>
     */
    protected function nonProductiveJobOrderOptions(Production $production): array
    {
        $workedRecently = JobOrderItemTask::whereIn(
            'id',
            ManufactureTaskSession::where('production_id', $production->id)
                ->whereNotNull('job_order_item_task_id')
                ->where('started_at', '>=', now()->subWeek())
                ->select('job_order_item_task_id')
        )->select('job_order_id');

        return JobOrder::where('production_id', $production->id)
            ->where(fn ($query) => $query->where('state', JobOrderStateEnum::CONFIRMED)->orWhereIn('id', $workedRecently))
            ->with('jobOrderItems.artefact')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (JobOrder $jobOrder) => [
                'id'    => $jobOrder->id,
                'label' => trim($jobOrder->reference.' · '.$jobOrder->jobOrderItems->pluck('artefact.code')->filter()->unique()->implode(', '), ' ·'),
            ])
            ->all();
    }

    protected function bandFeedback(ManufactureTaskSession $session): ?array
    {
        $standardRate = $session->recipeStandardRate();
        if ($standardRate === null) {
            return null;
        }

        $bands = ManufacturePayBand::effectiveAt($session->production_id, now())->get();
        if ($bands->isEmpty()) {
            return null;
        }

        $band0 = $bands->firstWhere('code', '0');

        $measuredBands = $bands
            ->filter(fn (ManufacturePayBand $band) => $band->target_multiplier !== null && $band->code != '0')
            ->sortBy('target_multiplier')
            ->values()
            ->map(fn (ManufacturePayBand $band) => [
                'code'                   => $band->code,
                'name'                   => $band->name,
                'hourly_rate'            => (float)$band->hourly_rate,
                'target_units_per_hour'  => round((float)$standardRate * (float)$band->target_multiplier, 1),
            ]);

        if ($measuredBands->isEmpty()) {
            return null;
        }

        return [
            'currency_symbol'   => $this->production->organisation->currency->symbol,
            'band0_hourly_rate' => $band0 ? (float)$band0->hourly_rate : 0.0,
            'bands'             => $measuredBands->all(),
            'session'           => [
                'started_at'    => $session->started_at,
                'break_minutes' => (int)($session->break_minutes ?? 0),
                'quantity_made' => (float)($session->quantity_made ?? 0),
            ],
        ];
    }

    /**
     * @param Collection<int, JobOrderItemTask> $steps
     * @return array<int, array{id: int, task_name: string, state: JobOrderItemTaskStateEnum, quantity_made: float, quantity_required: float, blocked_by_step: string|null, worked_by: string[], working_on_by: string[], seconds: int}>
     */
    protected function serializeSteps(Collection $steps, JobOrderItemTask $openTask): array
    {
        $steps->each(fn (JobOrderItemTask $step) => $step->setRelation('jobOrderItem', $openTask->jobOrderItem));
        $userName = fn (ManufactureTaskSession $session) => $session->user->contact_name ?: $session->user->username;

        return $steps->map(function (JobOrderItemTask $step) use ($steps, $userName) {
            $closedWork = $step->closedWork();

            return [
                'id'                => $step->id,
                'task_name'         => $step->manufactureTask->name,
                'state'             => $step->state,
                'quantity_made'     => (float)$step->quantity_made,
                'quantity_required' => (float)$step->quantity_required,
                'blocked_by_step'   => $step->blockingStep($steps)?->manufactureTask->name,
                'worked_by'         => $closedWork->map(fn (array $work) => $work['user']->contact_name ?: $work['user']->username)->unique()->values()->all(),
                'working_on_by'     => $step->sessions->where('state', ManufactureTaskSessionStateEnum::OPEN)->map($userName)->values()->all(),
                'seconds'           => (int)round($closedWork->sum('hours') * 3600),
            ];
        })->values()->all();
    }

    /**
     * @param Collection<int, JobOrderItemTask>|null $group the steps combined with this one, itself included
     */
    protected function serializeTask(JobOrderItemTask $task, ?Collection $group = null): array
    {
        $group      = $group ?? $this->combinedGroups[$task->id] ?? null;
        $isCombined = $group && $group->count() > 1;
        $members    = $isCombined ? $group : collect([$task]);

        return [
            'id'                  => $task->id,
            'state'               => $task->state,
            'position'            => $task->position,
            'manufacture_task_id' => $task->manufacture_task_id,
            'task_code'           => $task->manufactureTask->code,
            'task_name'           => $task->manufactureTask->name,
            'artefact_code'       => $members->map(fn (JobOrderItemTask $member) => $member->jobOrderItem->artefact->code)->unique()->implode(' + '),
            'artefact_name'       => $isCombined ? __('Combined batch') : $task->jobOrderItem->artefact->name,
            'job_order_reference' => $members->map(fn (JobOrderItemTask $member) => $member->jobOrder->reference)->unique()->implode(', '),
            'artisan'             => $task->jobOrderItem->artisan()?->contact_name,
            'is_mine'             => $this->employee && $members->contains(fn (JobOrderItemTask $member) => $member->jobOrderItem->artisanId() == $this->employee->id),
            'waiting_for'         => $members->flatMap(fn (JobOrderItemTask $member) => $this->missingMixes[$member->job_order_item_id] ??= array_column(GetJobOrderItemMissingMixes::run($member->jobOrderItem), 'code'))->unique()->values()->all(),
            'quantity_required'   => (float)$members->sum('quantity_required'),
            'quantity_made'       => (float)$members->sum('quantity_made'),
            'combined'            => $isCombined ? $members->map(fn (JobOrderItemTask $member) => [
                'id'                  => $member->id,
                'artefact_code'       => $member->jobOrderItem->artefact->code,
                'artefact_name'       => $member->jobOrderItem->artefact->name,
                'job_order_reference' => $member->jobOrder->reference,
                'quantity_required'   => (float)$member->quantity_required,
                'quantity_made'       => (float)$member->quantity_made,
            ])->values()->all() : null,
            'separate_route'      => $isCombined && $this->canCombine ? [
                'name'       => 'grp.models.job-order-item-task.separate',
                'parameters' => ['jobOrderItemTask' => $task->id],
                'method'     => 'patch',
            ] : null,
            'start_route'         => [
                'name'       => 'grp.models.job-order-item-task.session.store',
                'parameters' => ['jobOrderItemTask' => $task->id],
                'method'     => 'post',
            ],
        ];
    }

    /**
     * A combined batch is one job on the floor: the first unfinished step of the group stands for
     * all of them, the others leave the list.
     *
     * @param Collection<int, JobOrderItemTask> $openTasks
     * @return Collection<int, JobOrderItemTask>
     */
    protected function collapseCombined(Collection $openTasks): Collection
    {
        $hidden = [];
        foreach ($openTasks->whereNotNull('combined_task_id')->groupBy('combined_task_id') as $tasks) {
            $group = $tasks->first()->combinedGroup();
            if ($group->count() < 2) {
                continue;
            }
            $group->load(['jobOrderItem.artefact.manufactureTasks', 'jobOrderItem.tasks', 'jobOrderItem.employee', 'jobOrder.employee', 'manufactureTask']);

            $standsFor = $group->first(fn (JobOrderItemTask $member) => $member->state != JobOrderItemTaskStateEnum::DONE);
            $openTask  = $openTasks->firstWhere('id', $standsFor?->id);
            if (!$openTask) {
                continue;
            }

            $this->combinedGroups[$openTask->id] = $group->map(fn (JobOrderItemTask $member) => $member->id == $openTask->id ? $openTask : $member);
            array_push($hidden, ...$group->pluck('id')->reject(fn (int $id) => $id == $openTask->id)->all());
        }

        return $openTasks->reject(fn (JobOrderItemTask $task) => in_array($task->id, $hidden))->values();
    }

    /**
     * @return array<int, int>
     */
    protected function combinedTaskIdsOf(?Employee $employee): array
    {
        if (!$employee) {
            return [];
        }

        return JobOrderItemTask::where('job_order_item_tasks.production_id', $this->production->id)
            ->whereNotNull('job_order_item_tasks.combined_task_id')
            ->join('job_orders', 'job_orders.id', '=', 'job_order_item_tasks.job_order_id')
            ->join('job_order_items', 'job_order_items.id', '=', 'job_order_item_tasks.job_order_item_id')
            ->whereRaw('coalesce(job_order_items.employee_id, job_orders.employee_id) = ?', [$employee->id])
            ->distinct()
            ->pluck('job_order_item_tasks.combined_task_id')
            ->all();
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProduction::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'production'])),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.floor',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'production']),
                        ],
                        'label' => __('Manufacture floor'),
                    ],
                ],
            ]
        );
    }
}
