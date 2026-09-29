<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 20:45:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Comms\Mailshot\Filters\FilterDueToReorder;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Http\Resources\CRM\CustomerReorderProductsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Products the customer has bought on two or more different days, with how often they come back
 * for each one. Purchases on the same day count once, refunds are left out.
 */
class IndexCustomerReorderProducts extends OrgAction
{
    use WithCRMAuthorisation;

    public function handle(Customer $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('products.code', $value)
                    ->orWhereAnyWordStartWith('products.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $purchaseDays = DB::table('invoice_transactions')
            ->join('invoices', 'invoices.id', '=', 'invoice_transactions.invoice_id')
            ->where('invoice_transactions.customer_id', $parent->id)
            ->where('invoice_transactions.model_type', 'Product')
            ->where('invoice_transactions.quantity', '>', 0)
            ->whereNull('invoice_transactions.deleted_at')
            ->where('invoices.type', InvoiceTypeEnum::INVOICE->value)
            ->whereNull('invoices.deleted_at')
            ->selectRaw('invoice_transactions.model_id as product_id, invoice_transactions.date::date as ordered_on, sum(invoice_transactions.quantity) as quantity')
            ->groupByRaw('1, 2');

        $reorders = DB::query()
            ->fromSub($purchaseDays, 'purchase_days')
            ->selectRaw('product_id, count(*) as times_ordered, max(ordered_on) as last_ordered_on, avg(quantity) as average_quantity')
            ->selectRaw('round((max(ordered_on) - min(ordered_on))::numeric / (count(*) - 1)) as average_days_between')
            ->groupBy('product_id')
            ->havingRaw('count(*) >= 2');

        return QueryBuilder::for(Product::class)
            ->joinSub($reorders, 'reorders', 'reorders.product_id', '=', 'products.id')
            ->select([
                'products.id',
                'products.slug',
                'products.code',
                'products.name',
                'products.state',
                'reorders.times_ordered',
                'reorders.last_ordered_on',
                'reorders.average_quantity',
                'reorders.average_days_between',
            ])
            ->selectRaw('reorders.last_ordered_on + reorders.average_days_between::int as next_order_on')
            ->selectRaw('reorders.last_ordered_on + reorders.average_days_between::int between current_date - reorders.average_days_between::int and current_date + '.FilterDueToReorder::DAYS_AHEAD.' as is_due')
            ->defaultSort('-times_ordered')
            ->allowedSorts(['code', 'name', 'times_ordered', 'last_ordered_on', 'average_days_between', 'next_order_on'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('No product bought more than once yet')])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'times_ordered', label: __('Times ordered'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'average_quantity', label: __('Average quantity'), canBeHidden: true, align: 'right')
                ->column(key: 'average_days_between', label: __('Every (days)'), tooltip: __('Average days between orders of this product'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'last_ordered_on', label: __('Last ordered'), canBeHidden: false, sortable: true, type: 'date')
                ->column(key: 'next_order_on', label: __('Next order'), tooltip: __('Last ordered plus the average days between orders'), canBeHidden: false, sortable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $reorders): AnonymousResourceCollection
    {
        return CustomerReorderProductsResource::collection($reorders);
    }
}
