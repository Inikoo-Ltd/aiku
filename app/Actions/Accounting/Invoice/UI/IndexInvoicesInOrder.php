<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Sept 2025 14:52:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Accounting\InvoicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Ordering\Order;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class IndexInvoicesInOrder extends OrgAction
{
    public function handle(Order $order, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->whereNot('invoices.in_process', true);
        $queryBuilder->where('invoices.order_id', $order->id);
        $queryBuilder->leftjoin('organisations', 'invoices.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftjoin('shops', 'invoices.shop_id', '=', 'shops.id');

        $queryBuilder->defaultSort('-date')
            ->select([
                'invoices.id',
                'invoices.reference',
                'invoices.total_amount',
                'invoices.net_amount',
                'invoices.pay_status',
                'invoices.date',
                'invoices.type',
                'invoices.created_at',
                'invoices.updated_at',
                'invoices.in_process',
                'invoices.slug',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->leftJoin('currencies', 'invoices.currency_id', 'currencies.id')
            ->leftJoin('invoice_stats', 'invoices.id', 'invoice_stats.invoice_id');


        return $queryBuilder->allowedSorts(['number', 'pay_status', 'total_amount', 'net_amount', 'date', 'customer_name', 'reference'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Order $order, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $order) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->betweenDates(['date']);


            $table->withGlobalSearch();


            $table->withEmptyState(
                [
                    'title' => __('Order has not been invoiced'),
                    'count' => $order->invoices()->count ?? 0,
                ]
            );


            $table->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'pay_status', label: __('Payment'), canBeHidden: false, sortable: true, searchable: true, type: 'icon');
            $table->column(key: 'net_amount', label: __('net'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->defaultSort('-date');
        };
    }


    public function jsonResponse(LengthAwarePaginator $invoices): AnonymousResourceCollection
    {
        return InvoicesResource::collection($invoices);
    }


}
