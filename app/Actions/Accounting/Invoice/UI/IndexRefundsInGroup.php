<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Apr 2025 16:36:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\WithInvoicesSubNavigation;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Http\Resources\Accounting\RefundsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRefundsInGroup extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;
    use WithCustomerSubNavigation;
    use WithInvoicesSubNavigation;


    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('invoices.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(Invoice::class);
        $queryBuilder->where('invoices.type', InvoiceTypeEnum::REFUND);

        $queryBuilder->where('invoices.group_id', $group->id);

        $queryBuilder->leftjoin('organisations', 'invoices.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftjoin('customers', 'invoices.customer_id', '=', 'customers.id');
        $queryBuilder->leftjoin('shops', 'invoices.shop_id', '=', 'shops.id');

        $queryBuilder->defaultSort('-date')
            ->select([
                'invoices.id',
                'invoices.reference',
                'invoices.in_process',
                'invoices.total_amount',
                'invoices.net_amount',
                'invoices.pay_status',
                'invoices.date',
                'invoices.type',
                'invoices.created_at',
                'invoices.updated_at',
                'invoices.slug',
                'invoices.shop_id',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'invoices.original_invoice_id',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'organisations.name as organisation_name',
                'organisations.code as organisation_code',
                'organisations.slug as organisation_slug',
            ])
            ->leftJoin('currencies', 'invoices.currency_id', 'currencies.id')
            ->leftJoin('invoice_stats', 'invoices.id', 'invoice_stats.invoice_id');


        return $queryBuilder->allowedSorts(['number', 'pay_status', 'total_amount', 'net_amount', 'date', 'customer_name', 'reference', 'organisation_code', 'shop_code'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $group, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $group) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $stats     = $group->orderingStats;
            $noResults = __("This group hasn't been invoiced");

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $stats->number_invoices ?? 0,
                    ]
                );
            $table->betweenDates(['date']);
            $table->withGlobalSearch();

            $table->column(key: 'in_process', label: '', canBeHidden: false, type: 'icon');
            $table->column(key: 'organisation_code', label: __('org'), canBeHidden: false, searchable: true);
            $table->column(key: 'shop_code', label: __('shop'), canBeHidden: false, searchable: true);
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'customer_name', label: __('customer'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->defaultSort('-date');
        };
    }


    public function jsonResponse(LengthAwarePaginator $refunds): AnonymousResourceCollection
    {
        return RefundsResource::collection($refunds);
    }

    public function htmlResponse(LengthAwarePaginator $refunds, ActionRequest $request): Response
    {
        $subNavigation = [];

        $title = __('Refunds');

        $icon = [
            'icon'  => ['fal', 'fa-file-minus'],
            'title' => __('refunds')
        ];

        $afterTitle = [
            'label' => __('Group').': '.$this->group->name
        ];
        $iconRight  = null;
        $model      = null;
        $actions    = null;


        $routeName       = $request->route()->getName();
        $routeParameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Accounting/Refunds',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                'title'       => __('invoices'),
                'pageHead'    => [

                    'title'         => $title,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'        => RefundsResource::collection($refunds),


            ]
        )->table($this->tableStructure($this->group));
    }


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = [], ?string $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Refunds'),
                        'icon'  => 'fal fa-bars',

                    ],
                    'suffix' => $suffix
                ]
            ];
        };


        return array_merge(
            ShowGroupOverviewHub::make()->getBreadcrumbs(),
            $headCrumb(
                [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ]
            )
        );
    }
}
