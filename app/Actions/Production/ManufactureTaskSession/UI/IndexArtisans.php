<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 15:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowOperationsDashboard;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
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
            'from' => ['sometimes', 'date'],
            'to'   => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }

    public function htmlResponse(Production $production, ActionRequest $request): Response
    {
        $from = Carbon::parse(Arr::get($this->validatedData, 'from', now()->startOfWeek()->toDateString()))->startOfDay();
        $to   = Carbon::parse(Arr::get($this->validatedData, 'to', now()->toDateString()))->endOfDay();

        $sessions = ManufactureTaskSession::where('manufacture_task_sessions.production_id', $production->id)
            ->whereIn('manufacture_task_sessions.state', [
                ManufactureTaskSessionStateEnum::CLOSED,
                ManufactureTaskSessionStateEnum::VOIDED,
            ])
            ->whereBetween('ended_at', [$from, $to])
            ->with(['user', 'manufactureTask', 'jobOrderItemTask.jobOrderItem.artefact', 'jobOrderItemTask.jobOrder'])
            ->orderByDesc('ended_at')
            ->get();

        $artisans = $sessions
            ->groupBy('user_id')
            ->map(function ($userSessions) {
                $closed = $userSessions->where('state', ManufactureTaskSessionStateEnum::CLOSED);
                /** @var ManufactureTaskSession $first */
                $first = $userSessions->first();

                return [
                    'user_id'           => $first->user_id,
                    'worker'            => $first->user->contact_name ?: $first->user->username,
                    'number_sessions'   => $closed->count(),
                    'hours_worked'      => round($closed->sum(fn (ManufactureTaskSession $session) => $session->started_at->diffInSeconds($session->ended_at)) / 3600, 2),
                    'quantity_made'     => (float)$closed->sum('quantity_made'),
                    'quantity_rejected' => (float)$closed->sum('quantity_rejected'),
                    'earned'            => round($closed->sum(fn (ManufactureTaskSession $session) => $session->quantity_made * ($session->task_work_cost ?? 0)), 2),
                    'sessions'          => $userSessions->map(fn (ManufactureTaskSession $session) => [
                        'id'                  => $session->id,
                        'state'               => $session->state,
                        'task_name'           => $session->manufactureTask->name,
                        'artefact_code'       => $session->jobOrderItemTask->jobOrderItem->artefact->code,
                        'job_order_reference' => $session->jobOrderItemTask->jobOrder->reference,
                        'started_at'          => $session->started_at,
                        'ended_at'            => $session->ended_at,
                        'quantity_made'       => (float)$session->quantity_made,
                        'quantity_rejected'   => (float)$session->quantity_rejected,
                        'earned'              => round($session->quantity_made * ($session->task_work_cost ?? 0), 2),
                        'void_route'          => $this->canEdit && $session->state == ManufactureTaskSessionStateEnum::CLOSED ? [
                            'name'       => 'grp.models.manufacture-task-session.void',
                            'parameters' => ['manufactureTaskSession' => $session->id],
                        ] : null,
                    ])->values(),
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
                'artisans' => $artisans,
            ]
        );
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
                            'name'       => 'grp.org.productions.show.operations.artisans.index',
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
