<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufacturePayrollAdjustment\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowArtisansDashboard;
use App\Http\Resources\Production\ManufacturePayrollAdjustmentResource;
use App\InertiaTable\InertiaTable;
use App\Models\Production\ManufacturePayrollAdjustment;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Every change made to a pay week after it was locked for payroll, newest first: who, when, what, why, what it did to the
 * pay and who approved it. The record cannot be edited or deleted, so this is the place to look for money moved after payday.
 */
class IndexManufacturePayrollAdjustments extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return ShowArtisansDashboard::canSeeArtisanPay($request->user(), $this->production);
    }

    public function rules(): array
    {
        return [
            'awaiting_only' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function handle(Production $production, array $filters = []): LengthAwarePaginator
    {
        $query = ManufacturePayrollAdjustment::query()
            ->where('production_id', $production->id)
            ->when(Arr::get($filters, 'awaiting_only'), fn ($query) => $query->where('needs_approval', true)->whereNull('approved_at'))
            ->with(['production.organisation', 'user', 'changedBy', 'approvedBy']);

        return QueryBuilder::for($query)
            ->defaultSort('-created_at')
            ->allowedSorts(['created_at', 'pay_week', 'amount_delta'])
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withEmptyState([
                    'title'       => __('No changes after the payroll lock'),
                    'description' => __('Changes to a week already locked for payroll are listed here'),
                ])
                ->column(key: 'created_at', label: __('Changed'), canBeHidden: false, sortable: true)
                ->column(key: 'worker', label: __('Artisan'), canBeHidden: false)
                ->column(key: 'pay_week', label: __('Week'), canBeHidden: false, sortable: true)
                ->column(key: 'change', label: __('What changed'), canBeHidden: false)
                ->column(key: 'amount_delta', label: __('Pay'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'reason', label: __('Reason'), canBeHidden: false)
                ->column(key: 'changed_by', label: __('Changed by'), canBeHidden: false)
                ->column(key: 'approval', label: __('Accountant'), canBeHidden: false)
                ->defaultSort('-created_at');
        };
    }

    public function htmlResponse(LengthAwarePaginator $adjustments, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/ManufacturePayrollChanges',
            [
                'breadcrumbs'    => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'          => __('Payroll changes'),
                'pageHead'       => [
                    'title' => __('Payroll changes'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-history'],
                        'title' => __('Payroll changes'),
                    ],
                ],
                'awaiting_only'  => (bool) Arr::get($this->validatedData, 'awaiting_only', false),
                'awaiting_count' => ManufacturePayrollAdjustment::where('production_id', $this->production->id)->where('needs_approval', true)->whereNull('approved_at')->count(),
                'currency_code'  => $this->organisation->currency->code,
                'data'           => ManufacturePayrollAdjustmentResource::collection($adjustments),
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
                            'name'       => 'grp.org.productions.show.artisans.changes_after_lock',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Payroll changes'),
                    ],
                ],
            ]
        );
    }
}
