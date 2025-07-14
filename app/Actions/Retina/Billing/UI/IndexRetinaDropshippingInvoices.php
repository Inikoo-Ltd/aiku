<?php

/*
 * author Arya Permana - Kirin
 * created on 07-07-2025-10h-27m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Billing\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Accounting\DropshippingInvoicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaDropshippingInvoices extends RetinaAction
{
    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
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

        $queryBuilder->where('invoices.customer_id', $customer->id);



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
                'customer_sales_channels.id as customer_sales_channel_id',
                'customer_sales_channels.reference as customer_sales_channel_reference',
                'customer_sales_channels.slug as customer_sales_channel_slug',
                'customer_sales_channels.name as customer_sales_channel_name',
                'platforms.id as platform_id',
                'platforms.name as platform_name',
                'platforms.code as platform_code',
            ])
            ->leftJoin('currencies', 'invoices.currency_id', 'currencies.id')
            ->leftJoin('invoice_stats', 'invoices.id', 'invoice_stats.invoice_id')
            ->leftJoin('customer_sales_channels', 'customer_sales_channels.id', 'invoices.customer_sales_channel_id')
            ->leftJoin('platforms', 'platforms.id', 'customer_sales_channels.platform_id');




        return $queryBuilder->allowedSorts(['number', 'total_amount', 'net_amount', 'date', 'customer_name', 'reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Customer $customer, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $customer) {
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
                        'count' => $customer->number_invoices ?? 0,
                    ]
                );

            $table->column(key: 'type', label: '', canBeHidden: false, searchable: true, type: 'icon')
                ->defaultSort('reference');
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'customer_sales_channel_name', label: __('channel'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');




            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->defaultSort('reference');
        };
    }




    public function jsonResponse(LengthAwarePaginator $invoices): AnonymousResourceCollection
    {
        return DropshippingInvoicesResource::collection($invoices);
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
                'data'        => DropshippingInvoicesResource::collection($invoices),


            ]
        )->table($this->tableStructure($this->customer));
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }

    public function getBreadcrumbs(): array
    {

        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.dropshipping.invoices.index'
                            ],
                            'label' => __('Invoice dashboard'),
                        ]
                    ]
                ]
            );

    }
}
