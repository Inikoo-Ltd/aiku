<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 15:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowArtisansDashboard;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionUnderTargetReasonEnum;
use App\Models\Production\ManufactureTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexArtisans extends OrgAction
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
        ];
    }

    public function htmlResponse(Production $production, ActionRequest $request): Response
    {
        $from             = Carbon::parse(Arr::get($this->validatedData, 'from', now()->startOfWeek()->toDateString()))->startOfDay();
        $to               = Carbon::parse(Arr::get($this->validatedData, 'to', now()->toDateString()))->endOfDay();
        $manufactureTaskId = Arr::get($this->validatedData, 'manufacture_task_id');
        $underTargetOnly   = (bool) Arr::get($this->validatedData, 'under_target', false);

        $sessions = ManufactureTaskSession::where('manufacture_task_sessions.production_id', $production->id)
            ->whereIn('manufacture_task_sessions.state', [
                ManufactureTaskSessionStateEnum::CLOSED,
                ManufactureTaskSessionStateEnum::VOIDED,
            ])
            ->whereBetween('ended_at', [$from, $to])
            ->when($manufactureTaskId, fn ($query) => $query->where('manufacture_task_id', $manufactureTaskId))
            ->when($underTargetOnly, fn ($query) => $query->where('is_under_target', true))
            ->with(['user', 'manufactureTask', 'underTargetReviewer', 'jobOrderItemTask.jobOrderItem.artefact', 'jobOrderItemTask.jobOrder'])
            ->orderByDesc('ended_at')
            ->get();

        $totals = function (Collection $sessions): array {
            $closed = $sessions->where('state', ManufactureTaskSessionStateEnum::CLOSED);

            return [
                'number_sessions'   => $closed->count(),
                'hours'             => round($closed->sum(fn (ManufactureTaskSession $session) => $session->paidHours()), 2),
                'quantity_made'     => (float)$closed->sum('quantity_made'),
                'quantity_rejected' => (float)$closed->sum('quantity_rejected'),
                'earned'            => round($closed->sum(fn (ManufactureTaskSession $session) => $session->quantity_made * ($session->task_work_cost ?? 0)), 2),
            ];
        };

        $serializeSession = fn (ManufactureTaskSession $session) => [
            'id'                  => $session->id,
            'state'               => $session->state,
            'task_name'           => $session->manufactureTask->name,
            'artefact_code'       => $session->jobOrderItemTask->jobOrderItem->artefact->code,
            'job_order_reference' => $session->jobOrderItemTask->jobOrder->reference,
            'started_at'          => $session->started_at,
            'ended_at'            => $session->ended_at,
            'break_minutes'       => (int)$session->break_minutes,
            'quantity_made'       => (float)$session->quantity_made,
            'quantity_rejected'   => (float)$session->quantity_rejected,
            'earned'              => round($session->quantity_made * ($session->task_work_cost ?? 0), 2),
            'units_per_hour'      => $session->paidHours() > 0 ? round($session->quantity_made / $session->paidHours(), 1) : null,
            'standard_rate'       => $session->standard_rate === null ? null : (float)$session->standard_rate,
            'is_under_target'     => $session->is_under_target && $session->state == ManufactureTaskSessionStateEnum::CLOSED,
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
            ->map(function (Collection $userSessions) use ($totals, $serializeSession) {
                /** @var ManufactureTaskSession $first */
                $first         = $userSessions->first();
                $artisanTotals = $totals($userSessions);

                return [
                    'user_id'           => $first->user_id,
                    'worker'            => $first->user->contact_name ?: $first->user->username,
                    'number_sessions'   => $artisanTotals['number_sessions'],
                    'hours_worked'      => $artisanTotals['hours'],
                    'quantity_made'     => $artisanTotals['quantity_made'],
                    'quantity_rejected' => $artisanTotals['quantity_rejected'],
                    'earned'            => $artisanTotals['earned'],
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
            ->sortByDesc('quantity_made')
            ->values();

        return Inertia::render(
            'Org/Production/Artisans',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Artisans'),
                'pageHead'    => [
                    'title' => __('Artisans'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-user-hard-hat'],
                        'title' => __('Artisans'),
                    ],
                ],
                'period'   => [
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
                'manufacture_task_id' => $manufactureTaskId ? (int)$manufactureTaskId : null,
                'under_target'        => $underTargetOnly,
                'under_target_reasons' => ManufactureTaskSessionUnderTargetReasonEnum::labels(),
                'manufacture_tasks'   => ManufactureTask::withTrashed()
                    ->where('production_id', $production->id)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'artisans' => $artisans,
            ]
        );
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
                        'label' => __('Artisans'),
                    ],
                    'suffix' => $suffix,
                ],
            ]
        );
    }
}
