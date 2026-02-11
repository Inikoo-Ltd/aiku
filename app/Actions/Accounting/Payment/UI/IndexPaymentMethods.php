<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\Payment\UI;

use App\Actions\Accounting\Payment\WithPaymentSubNavigation;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Http\Resources\Accounting\PaymentMethodsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Payment;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexPaymentMethods extends OrgAction
{
    use WithPaymentSubNavigation;

    protected Organisation $parent;

    public function handle(Organisation $parent, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Payment::class);
        $queryBuilder->where('payments.organisation_id', $parent->id);
        $queryBuilder->leftJoin('currencies', 'payments.currency_id', 'currencies.id');
        $queryBuilder->whereNotNull('payments.method');

        return $queryBuilder
            ->defaultSort('method')
            ->select([
                'payments.method',
                DB::raw('COUNT(*) as number_payments'),
                DB::raw("SUM(CASE WHEN payments.status = '" . PaymentStatusEnum::SUCCESS->value . "' THEN payments.amount ELSE 0 END) as total_sales"),
                DB::raw("COUNT(CASE WHEN payments.status = '" . PaymentStatusEnum::SUCCESS->value . "' THEN 1 END) as number_success"),
                DB::raw("ROUND((COUNT(CASE WHEN payments.status = '" . PaymentStatusEnum::SUCCESS->value . "' THEN 1 END) * 100.0 / COUNT(*)), 2) as success_rate"),
                'currencies.code as currency_code'
            ])
            ->groupBy('payments.method', 'currencies.code')
            ->allowedSorts(['method', 'number_payments', 'total_sales', 'number_success', 'success_rate'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('method'), __('methods')])
                ->withModelOperations($modelOperations)
                ->defaultSort('method')
                ->column(key: 'method', label: __('Payment Method'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_payments', label: __('Total Payments'), canBeHidden: false, sortable: true, type: 'number')
                ->column(key: 'total_sales', label: __('Total Sales'), canBeHidden: false, sortable: true, type: 'number')
                ->column(key: 'number_success', label: __('Successful Payments'), canBeHidden: false, sortable: true, type: 'number')
                ->column(key: 'success_rate', label: __('Success Rate (%)'), canBeHidden: false, sortable: true, type: 'number')
                ->column(key: 'currency_code', label: __('Currency'), canBeHidden: false, searchable: true);
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $paymentMethods, ActionRequest $request): Response
    {
        $routeName       = $request->route()->getName();
        $routeParameters = $request->route()->originalParameters();
        $subNavigation   = $this->getPaymentSubNavigation($this->parent);

        return Inertia::render(
            'Org/Accounting/PaymentMethods',
            [
                'breadcrumbs' => $this->getBreadcrumbs($routeName, $routeParameters),
                'title'       => __('Payment Methods'),
                'pageHead'    => [
                    'subNavigation' => $subNavigation,
                    'icon'      => ['fal', 'fa-credit-card'],
                    'title'     => __('Payment Methods'),
                ],
                'data'        => PaymentMethodsResource::collection($paymentMethods),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function () use ($routeName, $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Payment Methods'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.accounting.payments.methods.index' =>
            array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb()
            ),
            default => []
        };
    }
}
