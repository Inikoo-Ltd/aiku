<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Sep 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Artisan\GetArtisanDepartments;
use App\Actions\Production\ManufactureArtisanDay\HydrateManufactureArtisanDays;
use App\Actions\Production\Production\UI\ShowArtisansDashboard;
use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayResolutionEnum;
use App\Enums\Production\ManufactureArtisanDay\ManufactureArtisanDayStatusEnum;
use App\Models\Production\ManufactureArtisanDay;
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
    private ?int $departmentId = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Production $production, Carbon $from, Carbon $to, ?int $departmentId = null): array
    {
        $departments = GetArtisanDepartments::run($production);
        $artisans    = array_map(fn (array $artisan) => Arr::except($artisan, ['lines', 'days']) + [
            'departments' => implode(', ', array_column($departments->get($artisan['employee_id'], []), 'name')),
        ], GetManufacturePayroll::run($production, $from, $to));

        if (!$departmentId) {
            return $artisans;
        }

        return array_values(array_filter(
            $artisans,
            fn (array $artisan) => in_array($departmentId, array_column($departments->get($artisan['employee_id'], []), 'id'))
        ));
    }

    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to'   => ['sometimes', 'date', 'after_or_equal:from'],
            'artefact_department_id' => ['sometimes', 'nullable', 'integer'],
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
        return ShowArtisansDashboard::canSeeArtisanPay($request->user(), $this->production);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);
        [$this->from, $this->to] = self::period($this->validatedData);
        $this->departmentId      = Arr::get($this->validatedData, 'artefact_department_id') ? (int) Arr::get($this->validatedData, 'artefact_department_id') : null;

        return $this->handle($production, $this->from, $this->to, $this->departmentId);
    }

    public function htmlResponse(array $artisans, ActionRequest $request): Response
    {
        $routeParameters = Arr::only($request->route()->originalParameters(), ['organisation', 'production']);
        $period          = ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
        $notAuthorised   = $this->notAuthorisedOvertime($this->production, $this->from, $this->to);

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
                'artefact_department_id' => $this->departmentId,
                'artefact_departments'   => GetArtisanDepartments::make()->options($this->production),
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
                        'not_authorised_overtime' => $notAuthorised[$artisan['user_id']] ?? null,
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
     * Overtime a supervisor did not authorise stays paid; it is listed here for the accountant and HR to deal with.
     *
     * @return array<int, array{minutes: int, days: array<int, array{date: string, minutes: int, note: string|null, by: string|null}>}>
     */
    private function notAuthorisedOvertime(Production $production, Carbon $from, Carbon $to): array
    {
        HydrateManufactureArtisanDays::run($production, $from, $to);

        return ManufactureArtisanDay::where('production_id', $production->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('status', ManufactureArtisanDayStatusEnum::OVER)
            ->where('resolution', ManufactureArtisanDayResolutionEnum::NOT_APPROVED)
            ->whereColumn('resolved_minutes', 'clocked_minutes')
            ->with('resolvedBy')
            ->orderBy('date')
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $days) => [
                'minutes' => $days->sum(fn (ManufactureArtisanDay $day) => $day->clocked_minutes - $day->expected_minutes),
                'days'    => $days->map(fn (ManufactureArtisanDay $day) => [
                    'date'    => $day->date->toDateString(),
                    'minutes' => $day->clocked_minutes - $day->expected_minutes,
                    'note'    => $day->resolution_note,
                    'by'      => $day->resolvedBy?->contact_name ?: $day->resolvedBy?->username,
                ])->values()->all(),
            ])
            ->all();
    }

    public static function payColumns(): array
    {
        return [
            'working' => __('Clocked hours'),
            'band_0'  => __('Band 0'),
            'band_1'  => __('Band 1'),
            'band_2'  => __('Band 2'),
            'band_3'  => __('Band 3'),
            'band_d'  => __('Development'),
            'band_dg' => __('Guru Dev'),
            'guru'       => __('Guru bonus'),
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
                ->column(key: 'departments', label: __('Department'), canBeHidden: false)
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
