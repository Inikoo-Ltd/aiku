<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Actions\Production\JobOrderItemTask\UI\ShowManufactureFloor;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The artisan's own finished work on the floor tablet: read-only, only the signed-in user's sessions, and no money.
 */
class ShowMyManufacturePerformance extends OrgAction
{
    public const array PERIODS = ['today', 'this_week', 'last_week', 'this_month', 'custom'];

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

        return $production;
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', Rule::in(self::PERIODS)],
            'from'   => ['required_if:period,custom', 'date'],
            'to'     => ['required_if:period,custom', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodDates(string $period, string $timezone, ?string $from = null, ?string $to = null): array
    {
        $now = now($timezone);

        return match ($period) {
            'today'      => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'last_week'  => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'custom'     => [Carbon::parse($from, $timezone)->startOfDay(), Carbon::parse($to, $timezone)->endOfDay()],
            default      => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
        };
    }

    public static function isOnTarget(ManufactureTaskSession $session): bool
    {
        return (float) $session->quantity_made / $session->paidHours() >= (float) $session->standard_rate;
    }

    public function htmlResponse(Production $production, ActionRequest $request): Response
    {
        $period     = Arr::get($this->validatedData, 'period', 'this_week');
        [$from, $to] = self::periodDates($period, $this->organisation->timezone?->name ?? config('app.timezone'), Arr::get($this->validatedData, 'from'), Arr::get($this->validatedData, 'to'));

        $sessions = ManufactureTaskSession::where('production_id', $production->id)
            ->where('user_id', $request->user()->id)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->whereBetween('ended_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->with(['manufactureTask', 'jobOrderItemTask.jobOrderItem.artefact', 'jobOrderItemTask.jobOrder', 'jobOrder', 'preparationReason'])
            ->orderByDesc('ended_at')
            ->get();

        [$nonProductive, $productive] = $sessions->partition(fn (ManufactureTaskSession $session) => $session->isNonProductive());
        $hours                       = fn (Collection $sessions) => round($sessions->sum(fn (ManufactureTaskSession $session) => $session->paidHours()), 2);
        $rows                        = $sessions->map(fn (ManufactureTaskSession $session) => [
            'id'                  => $session->id,
            'task_name'           => $session->isNonProductive() ? $session->activityLabel() : $session->manufactureTask->name,
            'is_non_productive'   => $session->isNonProductive(),
            'reason'              => $session->preparationReason?->name,
            'artefact_code'       => $session->isNonProductive() ? null : $session->artefactCode(),
            'job_order_reference' => $session->jobOrder?->reference ?? $session->jobOrderItemTask?->jobOrder?->reference,
            'ended_at'            => $session->ended_at,
            'hours'               => round($session->paidHours(), 2),
            'quantity_made'       => $session->isNonProductive() ? null : (float) $session->quantity_made,
            'quantity_rejected'   => $session->isNonProductive() ? null : (float) $session->quantity_rejected,
            'units_per_hour'      => !$session->isNonProductive() && $session->paidHours() > 0 ? round($session->quantity_made / $session->paidHours(), 1) : null,
            'standard_rate'       => $session->standard_rate === null ? null : (float) $session->standard_rate,
            'is_under_target'     => !$session->isNonProductive() && $session->standard_rate !== null && $session->paidHours() > 0 && !self::isOnTarget($session),
        ])->values()->all();
        $withTarget                  = $productive->filter(fn (ManufactureTaskSession $session) => $session->standard_rate !== null && $session->paidHours() > 0);

        return Inertia::render(
            'Org/Production/MyPerformance',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('My performance'),
                'pageHead'    => [
                    'title' => __('My performance'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-chart-line'],
                        'title' => __('My performance'),
                    ],
                ],
                'period'  => [
                    'key'  => $period,
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
                'totals'  => [
                    'hours'             => $hours($productive),
                    'quantity_made'     => round((float) $productive->sum('quantity_made'), 2),
                    'quantity_rejected' => round((float) $productive->sum('quantity_rejected'), 2),
                    'tasks'             => $productive->count(),
                    'on_target'         => $withTarget->filter(fn (ManufactureTaskSession $session) => self::isOnTarget($session))->count(),
                    'with_target'       => $withTarget->count(),
                    'preparation_hours' => $hours($nonProductive->where('activity_type', ManufactureTaskSessionActivityTypeEnum::PREPARATION)),
                    'cleaning_hours'    => $hours($nonProductive->where('activity_type', ManufactureTaskSessionActivityTypeEnum::CLEANING)),
                    'maintenance_hours' => $hours($nonProductive->where('activity_type', ManufactureTaskSessionActivityTypeEnum::MAINTENANCE)),
                ],
                'steps'   => $productive->groupBy('manufacture_task_id')
                    ->map(function (Collection $stepSessions) use ($hours) {
                        $stepHours   = $hours($stepSessions);
                        $made        = (float) $stepSessions->sum('quantity_made');
                        $rated       = $stepSessions->whereNotNull('standard_rate');
                        $ratedHours  = $rated->sum(fn (ManufactureTaskSession $session) => $session->paidHours());

                        return [
                            'task_name'      => $stepSessions->first()->manufactureTask->name,
                            'tasks'          => $stepSessions->count(),
                            'hours'          => $stepHours,
                            'quantity_made'  => round($made, 2),
                            'units_per_hour' => $stepHours > 0 ? round($made / $stepHours, 1) : null,
                            'standard_rate'  => $ratedHours > 0 ? round($rated->sum(fn (ManufactureTaskSession $session) => (float) $session->standard_rate * $session->paidHours()) / $ratedHours, 1) : null,
                        ];
                    })
                    ->sortByDesc('hours')
                    ->values(),
                'data'    => JsonResource::collection(new LengthAwarePaginator($rows, count($rows), max(1, count($rows)))),
            ]
        )->table($this->tableStructure());
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withEmptyState(['title' => __('No finished work in this period')])
                ->column(key: 'ended_at', label: __('Finished'), canBeHidden: false)
                ->column(key: 'task_name', label: __('Task'), canBeHidden: false)
                ->column(key: 'artefact_code', label: __('Product'), canBeHidden: false)
                ->column(key: 'job_order_reference', label: __('Job'), canBeHidden: false)
                ->column(key: 'hours', label: __('Time (h:mm)'), canBeHidden: false, type: 'number')
                ->column(key: 'quantity_made', label: __('Units'), canBeHidden: false, type: 'number')
                ->column(key: 'quantity_rejected', label: __('Rejected'), canBeHidden: false, type: 'number')
                ->column(key: 'units_per_hour', label: __('Units/h'), canBeHidden: false, type: 'number')
                ->column(key: 'standard_rate', label: __('Target/h'), canBeHidden: false, type: 'number');
        };
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $routeParameters = Arr::only($routeParameters, ['organisation', 'production']);

        return array_merge(
            ShowManufactureFloor::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.floor.my_performance',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('My performance'),
                    ],
                ],
            ]
        );
    }
}
