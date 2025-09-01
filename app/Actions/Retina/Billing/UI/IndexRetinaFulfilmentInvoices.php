<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Feb 2025 15:16:34 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Billing\UI;

use App\Actions\RetinaAction;
use App\Http\Resources\Accounting\InvoicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaFulfilmentInvoices extends RetinaAction
{
    private FulfilmentCustomer $parent;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(Invoice::class);

        $queryBuilder->where('invoices.customer_id', $fulfilmentCustomer->customer->id);



        $queryBuilder->defaultSort('-invoices.date')
            ->select([
                'invoices.reference',
                'invoices.total_amount',
                'invoices.net_amount',
                'invoices.date',
                'invoices.type',
                'invoices.created_at',
                'invoices.updated_at',
                'invoices.slug',
                'invoices.pay_status',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
            ])
            ->leftJoin('currencies', 'invoices.currency_id', 'currencies.id')
            ->leftJoin('invoice_stats', 'invoices.id', 'invoice_stats.invoice_id');




        return $queryBuilder->allowedSorts(['number', 'total_amount', 'net_amount', 'date', 'customer_name', 'reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(FulfilmentCustomer $fulfilmentCustomer, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $fulfilmentCustomer) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $noResults = __("No invoices found");

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $fulfilmentCustomer->customer->number_invoices ?? 0,
                    ]
                );

            $table->column(key: 'type', label: '', canBeHidden: false, searchable: true, type: 'icon')
                ->defaultSort('reference');
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');




            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->defaultSort('reference');
        };
    }




    public function jsonResponse(LengthAwarePaginator $invoices): AnonymousResourceCollection
    {
        return InvoicesResource::collection($invoices);
    }


    public function htmlResponse(LengthAwarePaginator $invoices, ActionRequest $request): Response
    {


        $afterTitle = null;
        $iconRight  = null;
        $model      = null;
        $actions    = null;

        $title      = __('Invoices');

        $icon  = [
            'icon'  => ['fal', 'fa-file-invoice-dollar'],
            'title' => __('Invoices')
        ];




        return Inertia::render(
            'Billing/RetinaInvoices',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('invoices'),
                'pageHead'    => [

                    'title'         => $title,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'actions'       => $actions
                ],
                'data'        => InvoicesResource::collection($invoices),


            ]
        )->table($this->tableStructure($this->parent));
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $request->user()->customer->fulfilmentCustomer;
        $this->initialisation($request);

        return $this->handle($request->user()->customer->fulfilmentCustomer);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowRetinaBillingDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'retina.fulfilment.billing.invoices.index',
                        ],
                        'label' => __('Invoices'),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ]
        );
    }
}
