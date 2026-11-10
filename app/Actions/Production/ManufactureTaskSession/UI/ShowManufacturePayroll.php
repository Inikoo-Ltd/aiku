<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Sep 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Artisan\GetArtisanWorkAreas;
use App\Actions\Production\ManufactureArtisanDay\HydrateManufactureArtisanDays;
use App\Actions\Production\Production\UI\ShowArtisansDashboard;
use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayResolutionEnum;
use App\Enums\Production\ManufactureOvertime\ManufactureOvertimeDecisionEnum;
use App\Models\Production\ManufactureArtisanDay;
use App\Models\Production\ManufactureOvertime;
use App\Models\Production\ManufactureToilTaken;
use App\Models\Production\ManufacturePayrollAdjustment;
use App\Models\Production\Production;
use App\Actions\Production\ManufactureTaskSession\GetManufacturePayroll;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowManufacturePayroll extends OrgAction
{
    private Carbon $from;
    private Carbon $to;
    private ?int $workAreaId = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Production $production, Carbon $from, Carbon $to, ?int $workAreaId = null): array
    {
        $workAreas = GetArtisanWorkAreas::run($production);
        $artisans  = array_map(fn (array $artisan) => Arr::except($artisan, ['lines', 'days']) + [
            'work_areas' => implode(', ', array_column($workAreas->get($artisan['employee_id'], []), 'name')),
        ], self::withOvertimeFacts($production, $from, $to, GetManufacturePayroll::run($production, $from, $to)));

        if (!$workAreaId) {
            return $artisans;
        }

        return array_values(array_filter(
            $artisans,
            fn (array $artisan) => in_array($workAreaId, array_column($workAreas->get($artisan['employee_id'], []), 'id'))
        ));
    }

    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to'   => ['sometimes', 'date', 'after_or_equal:from'],
            'work_area_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * Last week, Monday to Sunday, unless a period is asked for.
     */
    public static function period(array $modelData): array
    {
        return [
            Carbon::parse(Arr::get($modelData, 'from', now()->subWeek()->startOfWeek()->toDateString())),
            Carbon::parse(Arr::get($modelData, 'to', now()->subWeek()->endOfWeek()->toDateString())),
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return ShowArtisansDashboard::canSeeArtisanEarnings($request->user(), $this->production);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);
        [$this->from, $this->to] = self::period($this->validatedData);
        $this->workAreaId        = Arr::get($this->validatedData, 'work_area_id') ? (int) Arr::get($this->validatedData, 'work_area_id') : null;

        return $this->handle($production, $this->from, $this->to, $this->workAreaId);
    }

    public function htmlResponse(array $artisans, ActionRequest $request): Response
    {
        $routeParameters = Arr::only($request->route()->originalParameters(), ['organisation', 'production']);
        $period          = ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];

        return Inertia::render(
            'Org/Production/ManufacturePayroll',
            [
                'title'       => __('Payroll'),
                'breadcrumbs' => $this->getBreadcrumbs($routeParameters),
                'pageHead'    => [
                    'title' => __('Payroll'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-money-check-alt'],
                        'title' => __('Payroll'),
                    ],
                ],
                'period'               => $period,
                'work_area_id'         => $this->workAreaId,
                'work_areas'           => GetArtisanWorkAreas::make()->options($this->production),
                'payroll_export_route' => [
                    'name'       => 'grp.org.productions.show.artisans.payroll.export',
                    'parameters' => $routeParameters
                ],
                'show_pay_bands_to_artisans' => (bool) Arr::get($this->production->settings, 'show_pay_bands_to_artisans', false),
                'pay_bands_visible_route'    => [
                    'name'       => 'grp.org.productions.show.artisans.payroll.pay_bands_visible',
                    'parameters' => $routeParameters
                ],
                'payroll_changes'      => $this->payrollChanges($this->production, $this->from, $this->to, $request->user()),
                'data'                 => JsonResource::collection(new LengthAwarePaginator(
                    array_map(fn (array $artisan) => $artisan + [
                        'route'                   => [
                            'name'       => 'grp.org.productions.show.artisans.payroll.show',
                            'parameters' => $routeParameters + ['user' => $artisan['user_id']] + $period,
                        ],
                    ], $artisans),
                    count($artisans),
                    max(1, count($artisans))
                )),
            ]
        )->table($this->tableStructure(GetManufacturePayroll::make()->grandTotal($artisans)));
    }

    /**
     * Changes to the period's weeks made after they were locked, and changes to earlier weeks paid or deducted in this period.
     */
    private function payrollChanges(Production $production, Carbon $from, Carbon $to, User $viewer): array
    {
        [$settleStart, $settleEnd, $earlierThan] = ManufacturePayrollAdjustment::settledIn($from, $to);

        return ManufacturePayrollAdjustment::where('production_id', $production->id)
            ->where(fn ($query) => $query
                ->whereBetween('pay_week', [$earlierThan, $to->toDateString()])
                ->orWhere(fn ($query) => $query->whereBetween('created_at', [$settleStart, $settleEnd])->where('pay_week', '<', $earlierThan)))
            ->with(['production.organisation', 'user', 'changedBy', 'approvedBy'])
            ->orderByRaw('needs_approval and approved_at is null desc')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ManufacturePayrollAdjustment $adjustment) => $adjustment->toPayrollChange(true, $viewer) + [
                'settled_in_period' => $adjustment->pay_week->toDateString() < $earlierThan,
            ])
            ->all();
    }

    /**
     * Overtime a manager did not authorise stays paid and is listed for the accountant and HR, with the reason; overtime
     * nobody has decided on yet is flagged too. Each artisan also gets the TOIL taken in the period and the TOIL still owed
     * at its end.
     *
     * @param  array<int, array<string, mixed>>  $artisans  from GetManufacturePayroll
     * @return array<int, array<string, mixed>>
     */
    public static function withOvertimeFacts(Production $production, Carbon $from, Carbon $to, array $artisans): array
    {
        HydrateManufactureArtisanDays::run($production, $from, $to);

        $days = ManufactureArtisanDay::where('production_id', $production->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with(['resolvedBy', 'overtime.decidedBy'])
            ->orderBy('date')
            ->get();

        $minutesOver = fn (ManufactureArtisanDay $day) => max(0, ($day->clocked_minutes ?? 0) - ($day->expected_minutes ?? 0));
        $flagged     = fn (Collection $days, Closure $note) => $days->groupBy('user_id')->map(fn (Collection $userDays) => [
            'minutes' => $userDays->sum($minutesOver),
            'days'    => $userDays->map(fn (ManufactureArtisanDay $day) => ['date' => $day->date->toDateString(), 'minutes' => $minutesOver($day)] + $note($day))->values()->all(),
        ]);

        $notAuthorised = $flagged(
            $days->filter(fn (ManufactureArtisanDay $day) => $day->overtime
                ? $day->overtime->decision == ManufactureOvertimeDecisionEnum::NOT_APPROVED && $day->isOvertimeDecided() && $minutesOver($day) > 0
                : $day->resolution == ManufactureArtisanDayResolutionEnum::NOT_APPROVED && $day->isResolved()),
            fn (ManufactureArtisanDay $day) => $day->overtime
                ? ['note' => $day->overtime->reason, 'by' => $day->overtime->decidedBy?->contact_name ?: $day->overtime->decidedBy?->username]
                : ['note' => $day->resolution_note, 'by' => $day->resolvedBy?->contact_name ?: $day->resolvedBy?->username]
        );
        $awaiting = $flagged(
            $days->filter(fn (ManufactureArtisanDay $day) => $day->isOvertimeAwaitingDecision()),
            fn (ManufactureArtisanDay $day) => ['note' => null, 'by' => null]
        );

        $employeeIds = array_values(array_filter(array_column($artisans, 'employee_id')));
        $balances    = ManufactureOvertime::toilBalances($production, $to->toDateString(), $employeeIds);
        $taken       = ManufactureToilTaken::where('production_id', $production->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('employee_id', $employeeIds)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, sum(minutes) as minutes')
            ->pluck('minutes', 'employee_id');

        return array_map(fn (array $artisan) => $artisan + [
            'not_authorised_overtime' => $notAuthorised[$artisan['user_id']] ?? null,
            'awaiting_overtime'       => $awaiting[$artisan['user_id']] ?? null,
            'toil_taken_hours'        => round(((int) ($taken[$artisan['employee_id']] ?? 0)) / 60, 2),
            'toil_balance_hours'      => round(($balances[$artisan['employee_id']] ?? 0) / 60, 2),
        ], $artisans);
    }

    public static function payColumns(): array
    {
        return [
            'working' => __('Paid hours'),
            'band_0'  => __('Band 0'),
            'band_1'  => __('Band 1'),
            'band_2'  => __('Band 2'),
            'band_3'  => __('Band 3'),
            'band_d'  => __('Development'),
            'band_dg' => __('Guru Dev'),
            'guru'       => __('Guru bonus'),
            'weekend_overtime' => __('Weekend overtime'),
            'adjustment' => __('Adjustments to earlier weeks'),
        ];
    }

    public function tableStructure(array $grandTotal): Closure
    {
        return function (InertiaTable $table) use ($grandTotal) {
            $table
                ->withFooterRows(['data' => [$grandTotal]])
                ->withEmptyState(['title' => __('No work recorded in this period')])
                ->column(key: 'worker', label: __('Worker'), canBeHidden: false)
                ->column(key: 'work_areas', label: __('Area'), canBeHidden: false)
                ->column(key: 'artisan_pay_type', label: __('Pay type'), canBeHidden: false)
                ->column(key: 'is_agency_worker', label: __('Agency'), canBeHidden: false);

            foreach (self::payColumns() as $key => $label) {
                $table->column(key: $key, label: $label, canBeHidden: false, align: 'right');
                if ($key == 'working') {
                    $table->column(key: 'tablet_hours', label: __('Tablet hours'), canBeHidden: false, align: 'right');
                    $table->column(key: 'not_on_tablet_hours', label: __('Not on tablet (Band 0)'), canBeHidden: false, align: 'right');
                }
            }

            $table->column(key: 'total', label: __('Total'), canBeHidden: false, type: 'number');
            $table->column(key: 'toil_hours', label: __('TOIL hours (not paid)'), canBeHidden: false, align: 'right');
            $table->column(key: 'toil_balance_hours', label: __('TOIL balance'), canBeHidden: false, align: 'right');
        };
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
                            'name'       => 'grp.org.productions.show.artisans.payroll',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Payroll'),
                    ],
                    'suffix' => $suffix,
                ],
            ]
        );
    }
}
