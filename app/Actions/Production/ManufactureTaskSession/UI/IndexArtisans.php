<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 15:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Artisan\SetArtisanPayType;
use App\Actions\Production\ManufactureArtisanDay\HydrateManufactureArtisanDays;
use App\Actions\Production\Production\UI\ShowArtisansDashboard;
use App\Enums\Production\Artisan\ArtisanPayTypeEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionTimeEditReasonEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionUnderTargetReasonEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\ManufactureArtisanDay;
use App\Models\Production\ManufactureBreak;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTask;
use App\Models\Production\ManufacturePreparationReason;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexArtisans extends OrgAction
{
    private bool $canEditClosedPayWeek = false;
    private bool $canSeePay = false;

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);
        $this->canEditClosedPayWeek = $request->user()->authTo('sysadmin.edit');
        $this->canSeePay            = ShowArtisansDashboard::canSeeArtisanPay($request->user(), $this->production);

        return ShowArtisansDashboard::canSeeArtisans($request->user(), $this->production);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production);
    }

    public function handle(Production $production): Production
    {
        return $production;
    }

    public function rules(): array
    {
        return [
            'from'                => ['sometimes', 'date'],
            'to'                  => ['sometimes', 'date', 'after_or_equal:from'],
            'manufacture_task_id' => ['sometimes', 'integer'],
            'under_target'        => ['sometimes', 'boolean'],
            'artisan_pay_type'    => ['sometimes', 'nullable', Rule::enum(ArtisanPayTypeEnum::class)],
            'time_edited'         => ['sometimes', 'boolean'],
            'activity'            => ['sometimes', 'nullable', Rule::in(['production', ...array_map(fn (ManufactureTaskSessionActivityTypeEnum $activity) => $activity->value, ManufactureTaskSessionActivityTypeEnum::floorActivities())])],
            'preparation_reason'  => ['sometimes', 'nullable', 'string', 'regex:/^(none|\d+)$/'],
            'break_overrun'       => ['sometimes', 'boolean'],
        ];
    }

    public function htmlResponse(Production $production, ActionRequest $request): Response
    {
        $from             = Carbon::parse(Arr::get($this->validatedData, 'from', now()->startOfWeek()->toDateString()))->startOfDay();
        $to               = Carbon::parse(Arr::get($this->validatedData, 'to', now()->toDateString()))->endOfDay();
        $manufactureTaskId = Arr::get($this->validatedData, 'manufacture_task_id');
        $underTargetOnly   = (bool) Arr::get($this->validatedData, 'under_target', false);
        $payType           = Arr::get($this->validatedData, 'artisan_pay_type');
        $timeEditedOnly    = (bool) Arr::get($this->validatedData, 'time_edited', false);
        $activity          = Arr::get($this->validatedData, 'activity');
        $preparationReason = $activity == ManufactureTaskSessionActivityTypeEnum::PREPARATION->value ? Arr::get($this->validatedData, 'preparation_reason') : null;
        $breakOverrunOnly  = (bool) Arr::get($this->validatedData, 'break_overrun', false);
        $breakTolerance    = ManufactureBreak::overrunToleranceMinutes($production);

        $sessions = ManufactureTaskSession::where('manufacture_task_sessions.production_id', $production->id)
            ->whereIn('manufacture_task_sessions.state', [
                ManufactureTaskSessionStateEnum::CLOSED,
                ManufactureTaskSessionStateEnum::VOIDED,
            ])
            ->whereBetween('ended_at', [$from, $to])
            ->when($manufactureTaskId, fn ($query) => $query->where('manufacture_task_id', $manufactureTaskId))
            ->when($underTargetOnly, fn ($query) => $query->where('is_under_target', true))
            ->when($payType, fn ($query) => $query->where('artisan_pay_type', $payType))
            ->when($timeEditedOnly, fn ($query) => $query->whereNotNull('time_edited_at'))
            ->when($activity == 'production', fn ($query) => $query->whereNotNull('job_order_item_task_id'))
            ->when($activity && $activity != 'production', fn ($query) => $query->whereNull('job_order_item_task_id')->where('activity_type', $activity))
            ->when($preparationReason == 'none', fn ($query) => $query->whereNull('preparation_reason_id'))
            ->when($preparationReason && $preparationReason != 'none', fn ($query) => $query->where('preparation_reason_id', (int) $preparationReason))
            ->with(['user', 'employee', 'manufactureTask', 'underTargetReviewer', 'timeEditor', 'jobOrderItemTask.jobOrderItem.artefact', 'jobOrderItemTask.jobOrderItem.tasks.manufactureTask', 'jobOrderItemTask.jobOrder', 'jobOrder', 'preparationReason', 'movedInto.manufactureTask', 'movedPreparation'])
            ->orderByDesc('ended_at')
            ->get();

        $payTypeHistory = $this->payTypeHistory($sessions->pluck('employee')->filter()->unique('id'));

        $totals = function (Collection $sessions): array {
            $closed = $sessions->where('state', ManufactureTaskSessionStateEnum::CLOSED);

            return [
                'number_sessions'   => $closed->count(),
                'hours'             => round($closed->sum(fn (ManufactureTaskSession $session) => $session->paidHours()), 2),
                'quantity_made'     => round((float)$closed->sum('quantity_made'), 2),
                'quantity_rejected' => round((float)$closed->sum('quantity_rejected'), 2),
                'earned'            => $this->canSeePay ? round((float)$closed->sum('pay'), 2) : null,
            ];
        };

        $packSizes = ArtefactManufactureTask::packSizes();
        $canCorrect = fn (ManufactureTaskSession $session) => $this->canEdit
            && $session->state == ManufactureTaskSessionStateEnum::CLOSED
            && !$session->is_combined
            && in_array($session->jobOrderItemTask->jobOrder->state, JobOrderStateEnum::open())
            && $session->jobOrderItemTask->jobOrderItem->quantity_received <= 0;

        $userId       = $request->user()->id;
        $payWeekStart = now()->startOfWeek();
        $timeEdit     = fn (ManufactureTaskSession $session) => [
            'time_edit'                => $session->time_edited_at ? [
                'reason'              => ManufactureTaskSessionTimeEditReasonEnum::labels()[$session->time_edit_reason?->value] ?? null,
                'note'                => $session->time_edit_note,
                'edited_by'           => $session->timeEditor?->contact_name ?: $session->timeEditor?->username,
                'edited_at'           => $session->time_edited_at,
                'original_started_at' => $session->original_started_at,
                'original_ended_at'   => $session->original_ended_at,
            ] : null,
            'left_open_after_shift_at' => $session->left_open_after_shift_at,
            'times_route'              => ($this->canEdit || $this->canEditClosedPayWeek)
                && $session->state == ManufactureTaskSessionStateEnum::CLOSED
                && $session->user_id != $userId
                && ($this->canEditClosedPayWeek || $session->ended_at->greaterThanOrEqualTo($payWeekStart)) ? [
                    'name'       => 'grp.models.manufacture-task-session.times',
                    'parameters' => ['manufactureTaskSession' => $session->id],
                ] : null,
        ];

        $serializeSession = fn (ManufactureTaskSession $session) => $timeEdit($session) + [
            'id'                  => $session->id,
            'state'               => $session->state,
            'task_name'           => $session->manufactureTask->name,
            'artefact_code'       => $session->artefactCode(),
            'job_order_reference' => $session->jobOrderItemTask->jobOrder->reference,
            'started_at'          => $session->started_at,
            'ended_at'            => $session->ended_at,
            'break_minutes'       => (int)$session->break_minutes,
            'quantity_made'       => (float)$session->quantity_made,
            'pack_size'           => $packSizes[$session->jobOrderItemTask->jobOrderItem->artefact_id.':'.$session->manufacture_task_id] ?? null,
            'correct_route'       => $canCorrect($session) ? [
                'name'       => 'grp.models.manufacture-task-session.correct',
                'parameters' => ['manufactureTaskSession' => $session->id],
            ] : null,
            'other_steps'         => $canCorrect($session) ? $session->jobOrderItemTask->jobOrderItem->tasks
                ->reject(fn (JobOrderItemTask $step) => $step->id == $session->job_order_item_task_id || $step->combined_task_id)
                ->map(fn (JobOrderItemTask $step) => [
                    'id'                => $step->id,
                    'task_name'         => $step->manufactureTask->name,
                    'unit'              => $step->unit(),
                    'pack_size'         => $step->packSize(),
                    'quantity_left'     => max(0, (float)$step->quantity_required - (float)$step->quantity_made),
                ])->values()->all() : [],
            'quantity_rejected'   => (float)$session->quantity_rejected,
            'preparation_minutes' => $session->movedPreparation ? (int) round($session->movedPreparation->started_at->diffInSeconds($session->movedPreparation->ended_at) / 60) : null,
            'earned'              => $this->canSeePay ? round((float)$session->pay, 2) : null,
            'units_per_hour'      => $session->paidHours() > 0 ? round($session->quantity_made / $session->paidHours(), 1) : null,
            'standard_rate'       => $session->standard_rate === null ? null : (float)$session->standard_rate,
            'is_under_target'     => $session->is_under_target && $session->state == ManufactureTaskSessionStateEnum::CLOSED,
            'is_hourly'           => $session->artisan_pay_type == ArtisanPayTypeEnum::HOURLY,
            'under_target_review' => $session->under_target_reviewed_at ? [
                'reason'      => ManufactureTaskSessionUnderTargetReasonEnum::labels()[$session->under_target_reason?->value] ?? null,
                'note'        => $session->under_target_note,
                'reviewed_by' => $session->underTargetReviewer?->contact_name ?: $session->underTargetReviewer?->username,
                'reviewed_at' => $session->under_target_reviewed_at,
            ] : null,
            'review_route'        => $this->canEdit && $session->is_under_target && $session->state == ManufactureTaskSessionStateEnum::CLOSED ? [
                'name'       => 'grp.models.manufacture-task-session.under_target_review',
                'parameters' => ['manufactureTaskSession' => $session->id],
            ] : null,
            'void_route'          => $this->canEdit && $session->state == ManufactureTaskSessionStateEnum::CLOSED ? [
                'name'       => 'grp.models.manufacture-task-session.void',
                'parameters' => ['manufactureTaskSession' => $session->id],
            ] : null,
        ];

        $artisans = $sessions
            ->groupBy('user_id')
            ->map(function (Collection $allUserSessions) use ($totals, $serializeSession, $timeEdit, $from, $to, $payTypeHistory) {
                /** @var ManufactureTaskSession $first */
                $first         = $allUserSessions->first();
                $userSessions  = $allUserSessions->reject(fn (ManufactureTaskSession $session) => $session->isNonProductive());
                $artisanTotals = $totals($userSessions);
                $employee      = $allUserSessions->first(fn (ManufactureTaskSession $session) => $session->employee)?->employee;

                return [
                    'user_id'           => $first->user_id,
                    'worker'            => $first->user->contact_name ?: $first->user->username,
                    'artisan_pay_type'  => $employee?->artisan_pay_type,
                    'pay_type_route'    => $this->canEdit && $employee?->organisation_id == $this->organisation->id ? [
                        'name'       => 'grp.org.productions.show.artisans.pay_type.update',
                        'parameters' => ['organisation' => $this->organisation->slug, 'production' => $this->production->slug, 'employee' => $employee->id],
                    ] : null,
                    'pay_type_history'  => $employee ? $payTypeHistory->get($employee->id, []) : [],
                    'is_guru'           => $employee?->guru_since && $employee->guru_since->lessThanOrEqualTo($to) && (!$employee->guru_until || $employee->guru_until->greaterThanOrEqualTo($from->copy()->startOfDay())),
                    'guru_since'        => $employee?->guru_since?->toDateString(),
                    'guru_until'        => $employee?->guru_until?->toDateString(),
                    'guru_route'        => $this->canEdit && $employee?->organisation_id == $this->organisation->id ? [
                        'name'       => 'grp.org.productions.show.artisans.guru.update',
                        'parameters' => ['organisation' => $this->organisation->slug, 'production' => $this->production->slug, 'employee' => $employee->id],
                    ] : null,
                    'number_sessions'   => $artisanTotals['number_sessions'],
                    'hours_worked'      => $artisanTotals['hours'],
                    'quantity_made'     => $artisanTotals['quantity_made'],
                    'quantity_rejected' => $artisanTotals['quantity_rejected'],
                    'earned'            => $artisanTotals['earned'],
                    'non_productive'    => $this->nonProductiveSummary($allUserSessions->filter(fn (ManufactureTaskSession $session) => $session->isNonProductive()), $timeEdit),
                    'under_target_open' => $userSessions->filter(fn (ManufactureTaskSession $session) => $session->is_under_target && $session->state == ManufactureTaskSessionStateEnum::CLOSED && !$session->under_target_reviewed_at)->count(),
                    'jobs'              => $userSessions
                        ->groupBy('jobOrderItemTask.job_order_item_id')
                        ->map(function (Collection $jobSessions, int $jobOrderItemId) use ($serializeSession, $totals) {
                            /** @var ManufactureTaskSession $firstJobSession */
                            $firstJobSession = $jobSessions->first();

                            return [
                                'job_order_item_id'   => $jobOrderItemId,
                                'job_order_reference' => $firstJobSession->jobOrderItemTask->jobOrder->reference,
                                'artefact_code'       => $firstJobSession->jobOrderItemTask->jobOrderItem->artefact->code,
                                'steps'               => $jobSessions
                                    ->groupBy('manufacture_task_id')
                                    ->map(fn (Collection $stepSessions, int $manufactureTaskId) => [
                                        'manufacture_task_id' => $manufactureTaskId,
                                        'task_name'           => $stepSessions->first()->manufactureTask->name,
                                        'sessions'            => $stepSessions->map($serializeSession)->values(),
                                    ] + $totals($stepSessions))
                                    ->values(),
                            ] + $totals($jobSessions);
                        })
                        ->values(),
                ];
            })
            ->sortByDesc(fn (array $artisan) => [$artisan['quantity_made'], $artisan['non_productive']['hours']])
            ->values();

        return Inertia::render(
            'Org/Production/Artisans',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Performance'),
                'pageHead'    => [
                    'title' => __('Performance'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-user-hard-hat'],
                        'title' => __('Performance'),
                    ],
                ],
                'period'   => [
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
                'manufacture_task_id' => $manufactureTaskId ? (int)$manufactureTaskId : null,
                'under_target'        => $underTargetOnly,
                'time_edited'         => $timeEditedOnly,
                'time_edit_reasons'   => array_map(fn (ManufactureTaskSessionTimeEditReasonEnum $reason) => [
                    'value' => $reason->value,
                    'label' => ManufactureTaskSessionTimeEditReasonEnum::labels()[$reason->value],
                ], ManufactureTaskSessionTimeEditReasonEnum::supervisorReasons()),
                'left_open'           => $this->leftOpenSessions($production),
                'daily_hours_warnings' => $this->dailyHoursWarnings($production, $from, $to),
                'artisan_pay_type'    => $payType,
                'artisan_pay_types'   => ArtisanPayTypeEnum::labels(),
                'pay_type_effective_from' => SetArtisanPayType::effectiveFrom()->toDateString(),
                'currency_code'           => $this->organisation->currency->code,
                'can_see_pay'             => $this->canSeePay,
                'under_target_reasons' => ManufactureTaskSessionUnderTargetReasonEnum::labels(),
                'non_productive_activities' => array_map(fn (ManufactureTaskSessionActivityTypeEnum $activity) => [
                    'value' => $activity->value,
                    'label' => ManufactureTaskSessionActivityTypeEnum::labels()[$activity->value],
                ], ManufactureTaskSessionActivityTypeEnum::floorActivities()),
                'manufacture_tasks'   => ManufactureTask::withTrashed()
                    ->where('production_id', $production->id)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'activity'                  => $activity,
                'preparation_reason'        => $preparationReason,
                'preparation_reasons'       => ManufacturePreparationReason::forProduction($production)->map(fn (ManufacturePreparationReason $reason) => [
                    'id'       => $reason->id,
                    'name'     => $reason->name,
                    'is_other' => $reason->is_other,
                ])->all(),
                'retired_preparation_reasons' => ManufacturePreparationReason::onlyTrashed()->where('production_id', $production->id)
                    ->whereIn('id', ManufactureTaskSession::select('preparation_reason_id')->where('production_id', $production->id)->whereNotNull('preparation_reason_id'))
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'preparation_reasons_route' => $this->canEdit ? [
                    'name'       => 'grp.models.production.preparation_reasons.update',
                    'parameters' => ['production' => $production->id],
                ] : null,
                'activity_totals'           => $activity && $activity != 'production' ? $this->activityTotals($sessions) : null,
                'break_overrun'             => $breakOverrunOnly,
                'break_overrun_tolerance_minutes' => $breakTolerance,
                'break_overrun_tolerance_route'   => $this->canSeePay ? [
                    'name'       => 'grp.org.productions.show.artisans.break_overrun_tolerance',
                    'parameters' => ['organisation' => $this->organisation->slug, 'production' => $production->slug],
                ] : null,
                'short_preparation_minutes' => ManufactureTaskSession::shortPreparationMinutes($production),
                'short_preparation_route'   => $this->canSeePay ? [
                    'name'       => 'grp.org.productions.show.artisans.short_preparation',
                    'parameters' => ['organisation' => $this->organisation->slug, 'production' => $production->slug],
                ] : null,
                'breaks'                    => $this->breakDays($production, $from, $to, $breakTolerance, $breakOverrunOnly),
                'break_issues'              => $this->breakIssues($production, $breakTolerance, $request->user()->id),
                'artisans' => $artisans,
            ]
        );
    }

    /**
     * Preparation and cleaning are kept out of units, targets and earnings: hours per activity by day, week and job order.
     *
     * @param Collection<int, ManufactureTaskSession> $sessions
     */
    private function nonProductiveSummary(Collection $sessions, callable $timeEdit): array
    {
        $closed = $sessions->where('state', ManufactureTaskSessionStateEnum::CLOSED);

        $hoursByActivity = function (Collection $sessions): array {
            $hours = [];
            foreach (ManufactureTaskSessionActivityTypeEnum::floorActivities() as $activity) {
                $hours[$activity->value] = round($sessions->where('activity_type', $activity)->sum(fn (ManufactureTaskSession $session) => $session->paidHours()), 2);
            }

            return $hours;
        };

        $groupedHours = fn (callable $key, callable $label) => $closed
            ->groupBy($key)
            ->sortKeys()
            ->map(fn (Collection $group, string $groupKey) => ['key' => $groupKey, 'label' => $label($group->first(), $groupKey), 'hours' => $hoursByActivity($group)])
            ->values();

        return [
            'hours'      => round($closed->sum(fn (ManufactureTaskSession $session) => $session->paidHours()), 2),
            'pay'        => !$this->canSeePay || $closed->whereNotNull('pay')->isEmpty() ? null : round((float) $closed->sum('pay'), 2),
            'activities' => $hoursByActivity($closed),
            'days'       => $groupedHours(fn (ManufactureTaskSession $session) => $session->ended_at->toDateString(), fn ($session, string $date) => $date),
            'weeks'      => $groupedHours(fn (ManufactureTaskSession $session) => $session->ended_at->copy()->startOfWeek()->toDateString(), fn ($session, string $date) => $date),
            'job_orders' => $groupedHours(fn (ManufactureTaskSession $session) => (string) ($session->job_order_id ?? ''), fn (ManufactureTaskSession $session) => $session->jobOrder?->reference),
            'sessions'   => $sessions->map(fn (ManufactureTaskSession $session) => $timeEdit($session) + [
                'id'                  => $session->id,
                'state'               => $session->state,
                'activity'            => $session->activityLabel(),
                'reason'              => $session->activity_type == ManufactureTaskSessionActivityTypeEnum::PREPARATION ? ($session->preparationReason?->name ?? __('No reason recorded')) : null,
                'reason_note'         => $session->preparation_reason_note ? ($session->preparation_reason_note_en ?? $session->preparation_reason_note) : null,
                'reason_note_original' => $session->preparation_reason_note && $session->preparation_reason_note_en && $session->preparation_reason_note_en !== $session->preparation_reason_note ? $session->preparation_reason_note : null,
                'job_order_reference' => $session->jobOrder?->reference,
                'started_at'          => $session->started_at,
                'ended_at'            => $session->ended_at,
                'break_minutes'       => (int) $session->break_minutes,
                'pay'                 => !$this->canSeePay || $session->pay === null ? null : (float) $session->pay,
                'moved_into'          => $session->movedInto?->manufactureTask?->name,
                'undo_move_route'     => $session->movedInto && (($this->canEdit && ($session->movedInto->ended_at ?? now())->greaterThanOrEqualTo(now()->startOfWeek())) || $this->canEditClosedPayWeek) ? [
                    'name'       => 'grp.models.manufacture-task-session.undo_preparation_move',
                    'parameters' => ['manufactureTaskSession' => $session->id],
                ] : null,
                'void_route'          => $this->canEdit && $session->state == ManufactureTaskSessionStateEnum::CLOSED ? [
                    'name'       => 'grp.models.manufacture-task-session.void',
                    'parameters' => ['manufactureTaskSession' => $session->id],
                ] : null,
            ])->values(),
        ];
    }

    /**
     * Time and sessions of the chosen activity for the period, per artisan and, for Preparation, per reason:
     * "Other" is split by the English text typed, and sessions from before reasons were asked count as "No reason recorded".
     *
     * @param Collection<int, ManufactureTaskSession> $sessions
     */
    private function activityTotals(Collection $sessions): array
    {
        $closed = $sessions->where('state', ManufactureTaskSessionStateEnum::CLOSED);
        $totals = fn (Collection $group) => [
            'hours'           => round($group->sum(fn (ManufactureTaskSession $session) => $session->paidHours()), 2),
            'number_sessions' => $group->count(),
        ];

        $reasonNote = fn (ManufactureTaskSession $session) => trim($session->preparation_reason_note_en ?? $session->preparation_reason_note ?? '');

        return [
            'total'    => $totals($closed),
            'reasons'  => $closed->where('activity_type', ManufactureTaskSessionActivityTypeEnum::PREPARATION)
                ->groupBy(fn (ManufactureTaskSession $session) => (string) ($session->preparation_reason_id ?? 'none'))
                ->map(fn (Collection $group, string $key) => [
                    'key'   => $key,
                    'label' => $group->first()->preparationReason?->name ?? __('No reason recorded'),
                    'notes' => $group->first()->preparationReason?->is_other ? $group
                        ->groupBy(fn (ManufactureTaskSession $session) => mb_strtolower($reasonNote($session)))
                        ->map(fn (Collection $noteGroup) => ['label' => $reasonNote($noteGroup->first())] + $totals($noteGroup))
                        ->sortByDesc('hours')
                        ->values()
                        ->all() : [],
                ] + $totals($group))
                ->sortByDesc('hours')
                ->values()
                ->all(),
            'artisans' => $closed->groupBy('user_id')
                ->map(fn (Collection $group) => ['worker' => $group->first()->user->contact_name ?: $group->first()->user->username] + $totals($group))
                ->sortByDesc('hours')
                ->values()
                ->all(),
        ];
    }

    /**
     * Breaks taken on the tablet, per artisan and day: how many, the minutes planned, taken and over the plan.
     * With the overrun filter only the breaks that ran over by more than the tolerance are counted.
     */
    private function breakDays(Production $production, Carbon $from, Carbon $to, int $tolerance, bool $overrunOnly): array
    {
        return ManufactureBreak::where('production_id', $production->id)
            ->whereNotNull('planned_minutes')
            ->whereBetween('started_at', [$from, $to])
            ->with('user')
            ->orderBy('started_at')
            ->get()
            ->filter(fn (ManufactureBreak $break) => !$overrunOnly || $break->isOverrun($tolerance))
            ->groupBy(fn (ManufactureBreak $break) => $break->user_id.'|'.$break->started_at->toDateString())
            ->map(fn (Collection $breaks) => [
                'user_id'         => $breaks->first()->user_id,
                'worker'          => $breaks->first()->user->contact_name ?: $breaks->first()->user->username,
                'date'            => $breaks->first()->started_at->toDateString(),
                'number_breaks'   => $breaks->count(),
                'times'           => $breaks->map(fn (ManufactureBreak $break) => [
                    'id'              => $break->id,
                    'started_at'      => $break->started_at,
                    'ended_at'        => $break->ended_at,
                    'planned_minutes' => $break->planned_minutes,
                    'actual_minutes'  => $break->actualMinutes(),
                    'overrun_minutes' => $break->overrunMinutes(),
                    'is_overrun'      => $break->isOverrun($tolerance),
                ])->values()->all(),
                'planned_minutes' => $breaks->sum('planned_minutes'),
                'actual_minutes'  => $breaks->sum(fn (ManufactureBreak $break) => $break->actualMinutes()),
                'overrun_minutes' => $breaks->sum(fn (ManufactureBreak $break) => $break->overrunMinutes()),
                'number_overrun'  => $breaks->filter(fn (ManufactureBreak $break) => $break->isOverrun($tolerance))->count(),
                'is_open'         => $breaks->contains(fn (ManufactureBreak $break) => $break->ended_at === null),
            ])
            ->sortBy([['date', 'desc'], ['worker', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Breaks running over right now, and breaks left open after the shift that a supervisor has not set the end of yet.
     */
    private function breakIssues(Production $production, int $tolerance, int $viewerId): array
    {
        $payWeekStart = now()->startOfWeek();

        return ManufactureBreak::where('production_id', $production->id)
            ->whereNotNull('planned_minutes')
            ->whereNull('corrected_at')
            ->where('started_at', '>=', now()->subDays(ManufactureBreak::ISSUES_LOOKBACK_DAYS))
            ->where(fn ($query) => $query->whereNotNull('left_open_after_shift_at')->orWhereNull('ended_at'))
            ->with('user')
            ->orderBy('started_at')
            ->get()
            ->filter(fn (ManufactureBreak $break) => $break->left_open_after_shift_at || $break->isOverrun($tolerance))
            ->map(fn (ManufactureBreak $break) => [
                'id'                       => $break->id,
                'worker'                   => $break->user->contact_name ?: $break->user->username,
                'started_at'               => $break->started_at,
                'ended_at'                 => $break->ended_at,
                'planned_minutes'          => $break->planned_minutes,
                'actual_minutes'           => $break->actualMinutes(),
                'overrun_minutes'          => $break->overrunMinutes(),
                'left_open_after_shift_at' => $break->left_open_after_shift_at,
                'correct_route'            => $break->left_open_after_shift_at
                    && ($this->canEdit || $this->canEditClosedPayWeek)
                    && $break->user_id != $viewerId
                    && ($this->canEditClosedPayWeek || $break->started_at->greaterThanOrEqualTo($payWeekStart)) ? [
                        'name'       => 'grp.models.manufacture-break.correct_end',
                        'parameters' => ['manufactureBreak' => $break->id],
                    ] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Production sessions paused or still running when the operator's shift ended, flagged by production:stop_sessions_after_shift,
     * until the operator says they finished before the end of the shift, carries on after a clock-out over it, or a supervisor sets the times.
     */
    private function leftOpenSessions(Production $production): array
    {
        return ManufactureTaskSession::where('production_id', $production->id)
            ->whereIn('state', [ManufactureTaskSessionStateEnum::OPEN, ManufactureTaskSessionStateEnum::CLOSED])
            ->whereNotNull('job_order_item_task_id')
            ->whereNotNull('left_open_after_shift_at')
            ->whereNull('shift_end_resolved_at')
            ->with(['user', 'manufactureTask', 'jobOrder', 'jobOrderItemTask.jobOrder'])
            ->orderBy('started_at')
            ->get()
            ->map(function (ManufactureTaskSession $session) {
                $pause = $session->shiftEndPause();

                return [
                    'id'                       => $session->id,
                    'worker'                   => $session->user->contact_name ?: $session->user->username,
                    'task_name'                => $session->manufactureTask?->name ?? $session->activityLabel(),
                    'job_order_reference'      => $session->jobOrder?->reference ?? $session->jobOrderItemTask?->jobOrder?->reference,
                    'started_at'               => $session->started_at,
                    'left_open_after_shift_at' => $session->left_open_after_shift_at,
                    'paused_at'                => $pause?->started_at,
                    'ended_at'                 => $session->ended_at,
                    'status'                   => match (true) {
                        $session->state == ManufactureTaskSessionStateEnum::CLOSED => 'closed',
                        $pause && !$pause->ended_at => 'paused',
                        default => 'running',
                    },
                ];
            })
            ->all();
    }

    /**
     * @return array{count: int, route: array{name: string, parameters: array<string, mixed>}}
     */
    private function dailyHoursWarnings(Production $production, Carbon $from, Carbon $to): array
    {
        HydrateManufactureArtisanDays::run($production, $from, $to);

        return [
            'count' => ManufactureArtisanDay::where('production_id', $production->id)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->where('is_warning_open', true)
                ->count(),
            'route' => [
                'name'       => 'grp.org.productions.show.artisans.daily_hours',
                'parameters' => [
                    'organisation' => $production->organisation->slug,
                    'production'   => $production->slug,
                    'from'         => $from->toDateString(),
                    'to'           => $to->toDateString(),
                    'open_only'    => 1,
                ],
            ],
        ];
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $routeParameters = Arr::only($routeParameters, ['organisation', 'production']);

        return array_merge(
            (new ShowArtisansDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.artisans.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Performance'),
                    ],
                    'suffix' => $suffix,
                ],
            ]
        );
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, array<int, array{changed_at: string, by: string|null, from: string|null, to: string|null, effective_from: string}>>
     */
    private function payTypeHistory(Collection $employees): Collection
    {
        $labels = ArtisanPayTypeEnum::labels();

        return $employees->isEmpty() ? collect() : Employee::query()->whereIn('id', $employees->pluck('id'))
            ->with(['audits' => fn ($query) => $query->where('event', 'updated')->whereNotNull('new_values->artisan_pay_type')->with('user')->latest('id')])
            ->get()
            ->mapWithKeys(fn (Employee $employee) => [
                $employee->id => $employee->audits->map(fn ($audit) => [
                    'changed_at'     => $audit->created_at->toIso8601String(),
                    'by'             => $audit->user?->contact_name ?: $audit->user?->username,
                    'from'           => $labels[$audit->old_values['artisan_pay_type'] ?? ''] ?? null,
                    'to'             => $labels[$audit->new_values['artisan_pay_type']] ?? null,
                    'effective_from' => SetArtisanPayType::effectiveFrom($audit->created_at)->toDateString(),
                ])->values()->all(),
            ]);
    }
}
