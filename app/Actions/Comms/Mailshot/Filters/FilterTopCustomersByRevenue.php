<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 30 Sep 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FilterTopCustomersByRevenue
{
    public const int DEFAULT_PERCENTAGE = 20;

    public function __construct(private readonly ?int $shopId)
    {
    }

    /**
     * Customers whose net invoiced revenue (invoices less refunds) within the range puts them in the
     * top N% of the shop's customers who had any revenue in that range.
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $filter = Arr::get($filters, 'top_customers_by_revenue');

        if (!$this->shopId || !is_array($filter) || !is_array($filter['value'] ?? null)) {
            return $query;
        }

        $dateRange  = $filter['value']['date_range'] ?? null;
        $startDate  = is_array($dateRange) ? ($dateRange[0] ?? null) : null;
        $endDate    = is_array($dateRange) ? ($dateRange[1] ?? null) : null;
        $percentage = (float) ($filter['value']['percentage'] ?? self::DEFAULT_PERCENTAGE);

        if ($percentage <= 0 || $percentage > 100) {
            return $query;
        }

        $revenuePerCustomer = DB::table('invoices')
            ->select('invoices.customer_id')
            ->selectRaw('CUME_DIST() OVER (ORDER BY SUM(invoices.net_amount) DESC) AS revenue_rank')
            ->where('invoices.shop_id', $this->shopId)
            ->where('invoices.in_process', false)
            ->whereNull('invoices.deleted_at')
            ->whereNotNull('invoices.customer_id')
            ->when($startDate, fn (Builder $invoices) => $invoices->whereDate('invoices.date', '>=', $startDate))
            ->when($endDate, fn (Builder $invoices) => $invoices->whereDate('invoices.date', '<=', $endDate))
            ->groupBy('invoices.customer_id')
            ->havingRaw('SUM(invoices.net_amount) > 0');

        $query->whereIn('customers.id', function (Builder $subQuery) use ($revenuePerCustomer, $percentage) {
            $subQuery->select('ranked.customer_id')
                ->fromSub($revenuePerCustomer, 'ranked')
                ->where('ranked.revenue_rank', '<=', $percentage / 100);
        });

        return $query;
    }
}
