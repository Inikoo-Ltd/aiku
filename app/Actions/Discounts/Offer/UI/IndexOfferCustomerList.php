<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 02 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\OrgAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferHasCustomer;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexOfferCustomerList extends OrgAction
{
    public function handle(Offer $offer, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.name', $value)
                    ->orWhereStartWith('customers.reference', $value)
                    ->orWhereStartWith('offer_has_customers.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $firstOrderWithVoucher = DB::table('orders')
            ->select(['customer_id', DB::raw('MIN(id) AS order_id')])
            ->where('offer_voucher_id', $offer->id)
            ->whereNotIn('state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->whereNull('deleted_at')
            ->groupBy('customer_id');

        $query = QueryBuilder::for(OfferHasCustomer::class)
            ->where('offer_has_customers.offer_id', $offer->id)
            ->join('customers', 'customers.id', '=', 'offer_has_customers.customer_id')
            ->leftJoinSub($firstOrderWithVoucher, 'voucher_orders', 'voucher_orders.customer_id', '=', 'customers.id')
            ->leftJoin('orders', 'orders.id', '=', 'voucher_orders.order_id')
            ->join('shops', 'shops.id', '=', 'offer_has_customers.shop_id')
            ->join('organisations', 'organisations.id', '=', 'shops.organisation_id');

        $customerNameSort = AllowedSort::field('customer_name', 'customers.name');

        return $query->defaultSort($customerNameSort)
            ->select([
                'offer_has_customers.id',
                'offer_has_customers.code',
                'shops.slug as shop_slug',
                'organisations.slug as organisation_slug',
                'customers.slug as customer_slug',
                'customers.reference as customer_reference',
                'customers.name as customer_name',
                'customers.state as customer_state',
                'orders.slug as order_slug',
                'orders.reference as order_reference',
                'orders.date as order_date',
            ])
            ->allowedSorts([
                $customerNameSort,
                AllowedSort::field('code', 'offer_has_customers.code'),
                AllowedSort::field('order_date', 'orders.date'),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Offer $offer, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($offer, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->withGlobalSearch();
            $table->withEmptyState([
                'icons'       => ['fal fa-users'],
                'title'       => __('No customers on this voucher'),
                'description' => __('Only vouchers created for a customer list have customers here.'),
            ]);

            $table->column(key: 'customer_name', label: __('Customer'), sortable: true);
            $table->column(key: 'customer_state', label: __('Customer state'));
            if ($offer->hasUniqueCustomerCodes()) {
                $table->column(key: 'code', label: __('Voucher code'), sortable: true);
            }
            $table->column(key: 'order_reference', label: __('Used in order'));
            $table->column(key: 'order_date', label: __('Used on'), sortable: true, align: 'right');

            $table->defaultSort('customer_name');
        };
    }
}
