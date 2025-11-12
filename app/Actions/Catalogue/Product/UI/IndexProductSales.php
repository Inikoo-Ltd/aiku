<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Mon, 10 Nov 2025 15:52:15 Western Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Product;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductSales extends OrgAction
{
    public function handle(Product $product, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('invoices.reference', 'like', "%{$value}%")
                    ->orWhere('customers.name', 'like', "%{$value}%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $morphType = (new Product())->getMorphClass();

        return QueryBuilder::for(Invoice::class)
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'invoices.currency_id')
            ->leftJoin('shops', 'shops.id', '=', 'invoices.shop_id')
            ->leftJoin('organisations', 'organisations.id', '=', 'invoices.organisation_id')
            ->where('invoices.in_process', false)
            ->where('invoices.type', InvoiceTypeEnum::INVOICE)
            ->whereHas('invoiceTransactions', function ($q) use ($product, $morphType) {
                $q->where('invoice_transactions.model_type', $morphType)
                    ->where('invoice_transactions.model_id', $product->id);
            })
            ->select([
                'invoices.id',
                'invoices.slug',
                'invoices.reference',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'invoices.date',
                'invoices.pay_status',
                'currencies.code as currency_code',
                'shops.slug as shop_slug',
                'organisations.slug as organisation_slug',
                DB::raw("(
                    SELECT SUM(invoice_transactions.net_amount)
                    FROM invoice_transactions
                    WHERE invoice_transactions.invoice_id = invoices.id
                      AND invoice_transactions.model_type = '".addslashes($morphType)."'
                      AND invoice_transactions.model_id = ".$product->id."
                ) AS total_sales"),
            ])
            ->defaultSort('-date')
            ->allowedSorts(['reference', 'customer_name', 'date', 'pay_status', 'total_sales'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->column('reference', __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column('customer_name', __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column('date', __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column('pay_status', __('Payment'), canBeHidden: false, sortable: true, searchable: true, type: 'icon')
                ->column('total_sales', __('Total Sales'), canBeHidden: false, sortable: true, type: 'number')
                ->defaultSort('-date');
        };
    }
}
