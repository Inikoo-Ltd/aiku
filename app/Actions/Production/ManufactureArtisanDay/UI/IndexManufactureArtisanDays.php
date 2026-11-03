<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Oct 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureArtisanDay\UI;

use App\Actions\OrgAction;
use App\Actions\Production\ManufactureArtisanDay\HydrateManufactureArtisanDays;
use App\Actions\Production\Production\UI\ShowArtisansDashboard;
use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayShortReasonEnum;
use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayStatusEnum;
use App\Http\Resources\Production\ManufactureArtisanDayResource;
use App\InertiaTable\InertiaTable;
use App\Models\Production\ManufactureArtisanDay;
use App\Models\Production\ManufactureHoursTolerance;
use App\Models\Production\ManufacturePayrollAdjustment;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Each artisan's day against their shift: over or under it by more than the tolerance stays flagged until a supervisor
 * approves the overtime or not, or records why the day was short.
 */
class IndexManufactureArtisanDays extends OrgAction
{
    private Carbon $from;
    private Carbon $to;

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
        ]);

        return ShowArtisansDashboard::canSeeArtisans($request->user(), $this->production);
    }

    public function rules(): array
    {
        return [
            'from'      => ['sometimes', 'date'],
            'to'        => ['sometimes', 'date', 'after_or_equal:from'],
            'open_only' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function handle(Production $production, array $filters = []): LengthAwarePaginator
    {
        $this->from = Carbon::parse(Arr::get($filters, 'from', now()->startOfWeek()->toDateString()));
        $this->to   = Carbon::parse(Arr::get($filters, 'to', now()->toDateString()));
        $this->from = $this->from->max($this->to->copy()->subDays(HydrateManufactureArtisanDays::MAX_DAYS));

        HydrateManufactureArtisanDays::run($production, $this->from, $this->to);

        $query = ManufactureArtisanDay::query()
            ->where('manufacture_artisan_days.production_id', $production->id)
            ->whereBetween('manufacture_artisan_days.date', [$this->from->toDateString(), $this->to->toDateString()])
            ->when(Arr::get($filters, 'open_only'), fn ($query) => $query->where('is_warning_open', true))
            ->leftJoin('users', 'users.id', '=', 'manufacture_artisan_days.user_id')
            ->leftJoin('users as resolvers', 'resolvers.id', '=', 'manufacture_artisan_days.resolved_by_user_id')
            ->with('employee.organisation.timezone')
            ->select('manufacture_artisan_days.*')
            ->selectRaw("coalesce(nullif(users.contact_name, ''), users.username) as worker")
            ->selectRaw("coalesce(nullif(resolvers.contact_name, ''), resolvers.username) as resolved_by")
            ->selectRaw('coalesce(manufacture_artisan_days.clocked_minutes, 0) - manufacture_artisan_days.expected_minutes as difference_minutes');

        return QueryBuilder::for($query)
            ->defaultSort('-date')
            ->allowedSorts(['date', 'worker', 'clocked_minutes', 'recorded_minutes', 'difference_minutes'])
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withEmptyState([
                    'title'       => __('No days to show'),
                    'description' => __('Days appear once an artisan clocks in or records work on the floor'),
                ])
                ->column(key: 'date', label: __('Day'), canBeHidden: false, sortable: true)
                ->column(key: 'worker', label: __('Artisan'), canBeHidden: false, sortable: true)
                ->column(key: 'shift', label: __('Shift'), canBeHidden: false)
                ->column(key: 'expected_minutes', label: __('Expected'), canBeHidden: false, align: 'right')
                ->column(key: 'clocked_minutes', label: __('Clocked'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'recorded_minutes', label: __('Tablet'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'difference_minutes', label: __('Difference'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'status', label: __('Check'), canBeHidden: false)
                ->defaultSort('-date');
        };
    }

    public function htmlResponse(LengthAwarePaginator $days, ActionRequest $request): Response
    {
        $production = $this->production;

        return Inertia::render(
            'Org/Production/ArtisanDays',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Daily hours'),
                'pageHead'    => [
                    'title' => __('Daily hours'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-user-clock'],
                        'title' => __('Daily hours'),
                    ],
                ],
                'period'             => ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()],
                'open_only'          => (bool) Arr::get($this->validatedData, 'open_only', false),
                'open_count'         => ManufactureArtisanDay::where('production_id', $production->id)
                    ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
                    ->where('is_warning_open', true)
                    ->count(),
                'can_resolve'        => $this->canEdit,
                'payroll_lock'       => [
                    'locked_before' => ManufacturePayrollAdjustment::lockedBefore($production)->toDateString(),
                    'can_change'    => ManufacturePayrollAdjustment::canChangeLockedWeek($request->user(), $production),
                ],
                'can_set_tolerance'  => ShowArtisansDashboard::canSeeArtisanPay($request->user(), $production),
                'tolerance_route'    => [
                    'name'       => 'grp.org.productions.show.artisans.daily_hours.tolerance',
                    'parameters' => [$production->organisation->slug, $production->slug],
                ],
                'tolerance_minutes'  => ManufactureHoursTolerance::minutesOn($production->id, now()->toDateString()),
                'tolerances'         => ManufactureHoursTolerance::where('production_id', $production->id)
                    ->with('setBy')
                    ->orderByDesc('effective_from')
                    ->get()
                    ->map(fn (ManufactureHoursTolerance $tolerance) => [
                        'tolerance_minutes' => $tolerance->tolerance_minutes,
                        'effective_from'    => $tolerance->effective_from->toDateString(),
                        'set_by'            => $tolerance->setBy?->contact_name ?: $tolerance->setBy?->username,
                        'set_at'            => $tolerance->updated_at,
                    ])
                    ->all(),
                'default_tolerance_minutes' => ManufactureHoursTolerance::DEFAULT_MINUTES,
                'short_reasons'      => ManufactureArtisanDayShortReasonEnum::labels(),
                'statuses'           => ManufactureArtisanDayStatusEnum::labels(),
                'performance_route'  => [
                    'name'       => 'grp.org.productions.show.artisans.index',
                    'parameters' => ['organisation' => $production->organisation->slug, 'production' => $production->slug],
                ],
                'data'               => ManufactureArtisanDayResource::collection($days),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $routeParameters = Arr::only($routeParameters, ['organisation', 'production']);

        return array_merge(
            (new ShowArtisansDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.artisans.daily_hours',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Daily hours'),
                    ],
                ],
            ]
        );
    }
}
