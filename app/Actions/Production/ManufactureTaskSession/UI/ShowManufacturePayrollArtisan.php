<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Oct 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession\UI;

use App\Actions\Production\Production\UI\ShowArtisansDashboard;
use App\Actions\OrgAction;
use App\Actions\Production\ManufactureTaskSession\GetManufacturePayroll;
use App\InertiaTable\InertiaTable;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowManufacturePayrollArtisan extends OrgAction
{
    private Carbon $from;
    private Carbon $to;
    private User $user;

    public function handle(Production $production, User $user, Carbon $from, Carbon $to): ?array
    {
        return GetManufacturePayroll::run($production, $from, $to, $user->id)[0] ?? null;
    }

    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to'   => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return ShowArtisansDashboard::canSeeArtisanPay($request->user(), $this->production);
    }

    public function asController(Organisation $organisation, Production $production, User $user, ActionRequest $request): ?array
    {
        abort_unless($user->group_id == $production->group_id, 404);
        $this->user = $user;
        $this->initialisationFromProduction($production, $request);
        [$this->from, $this->to] = ShowManufacturePayroll::period($this->validatedData);

        return $this->handle($production, $user, $this->from, $this->to);
    }

    public function htmlResponse(?array $artisan, ActionRequest $request): Response
    {
        $routeParameters = Arr::only($request->route()->originalParameters(), ['organisation', 'production']);
        $period          = ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
        $worker          = $this->user->contact_name ?: $this->user->username;
        $lines           = $artisan['lines'] ?? [];
        $days            = $artisan['days'] ?? [];

        return Inertia::render(
            'Org/Production/ManufacturePayrollArtisan',
            [
                'title'       => __('Payroll').' '.$worker,
                'breadcrumbs' => (new ShowManufacturePayroll())->getBreadcrumbs($routeParameters, $worker),
                'pageHead'    => [
                    'title' => $worker,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-money-check-alt'],
                        'title' => __('Payroll'),
                    ],
                ],
                'period'      => $period,
                'back_route'  => [
                    'name'       => 'grp.org.productions.show.artisans.payroll',
                    'parameters' => $routeParameters + $period,
                ],
                'days'        => JsonResource::collection(new LengthAwarePaginator($days, count($days), max(1, count($days)))),
                'data'        => JsonResource::collection(new LengthAwarePaginator($lines, count($lines), max(1, count($lines)))),
            ]
        )->table($this->daysTableStructure($artisan))->table($this->tableStructure($artisan));
    }

    /**
     * The same columns as the payroll page, one row per day.
     */
    public function daysTableStructure(?array $artisan): Closure
    {
        return function (InertiaTable $table) use ($artisan) {
            $table
                ->name('days')
                ->pageName('daysPage')
                ->withFooterRows(['data' => $artisan ? [['date' => __('Total')] + Arr::except($artisan, ['lines', 'days'])] : []])
                ->withEmptyState(['title' => __('No work recorded in this period')])
                ->column(key: 'date', label: __('Day'), canBeHidden: false);

            foreach (ShowManufacturePayroll::payColumns() as $key => $label) {
                $table->column(key: $key, label: $label, canBeHidden: false, align: 'right');
                if ($key == 'working') {
                    $table->column(key: 'tablet_hours', label: __('Tablet hours'), canBeHidden: false, align: 'right');
                    $table->column(key: 'not_on_tablet_hours', label: __('Not on tablet (Band 0)'), canBeHidden: false, align: 'right');
                }
            }

            $table->column(key: 'total', label: __('Total'), canBeHidden: false, type: 'number');
        };
    }

    public function tableStructure(?array $artisan): Closure
    {
        return function (InertiaTable $table) use ($artisan) {
            $table
                ->withFooterRows(['data' => $artisan ? [[
                    'date'   => __('Total'),
                    'hours'  => $artisan['working_hours'],
                    'amount' => $artisan['total'],
                ]] : []])
                ->withEmptyState(['title' => __('No work recorded in this period')])
                ->column(key: 'date', label: __('Day'), canBeHidden: false)
                ->column(key: 'job_order_reference', label: __('Job'), canBeHidden: false)
                ->column(key: 'artefact_code', label: __('Product'), canBeHidden: false)
                ->column(key: 'step', label: __('Step'), canBeHidden: false)
                ->column(key: 'band', label: __('Band'), canBeHidden: false)
                ->column(key: 'hours', label: __('Hours'), canBeHidden: false, type: 'number')
                ->column(key: 'units', label: __('Units'), canBeHidden: false, type: 'number')
                ->column(key: 'band_rate', label: __('Rate'), canBeHidden: false, type: 'number')
                ->column(key: 'amount', label: __('Amount'), canBeHidden: false, type: 'number');
        };
    }
}
