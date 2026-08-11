<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 11 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The per-channel figures behind every management-level marketing screen, in the parent's own
 * currency. Each query buckets by whatever `$groupBy` names - the channel type for a dashboard, the
 * shop for a breakdown - and `$onlyType` narrows it to a single channel, so the roll-up and the
 * drill-down are the same arithmetic rather than two implementations that have to be kept level.
 */
trait WithAggregatedChannelQueries
{
    use WithAttributionWindow;

    /**
     * A figure can only be read as a share of another if both cover the same stretch of time.
     * Attribution has only been recording since its first touch, so an unbounded window would divide
     * every email we ever sent by the revenue of the fortnight we have been measuring.
     */
    protected function clipToAttributionStart(?Carbon $from): ?Carbon
    {
        $startedAt = GetAttributionStartedAt::run();

        if (!$startedAt) {
            return $from;
        }

        return $from && $from->isAfter($startedAt) ? $from : $startedAt;
    }

    /**
     * Shops may each override the attribution window, so they are grouped by the window they actually
     * use and queried once per distinct value - in practice one query, since overrides are rare.
     *
     * @param Collection<int, Shop> $shops
     *
     * @return Collection<int, array{window: int, shop_ids: array<int, int>}>
     */
    protected function shopsByWindow(Collection $shops): Collection
    {
        return $shops
            ->groupBy(fn (Shop $shop) => GetAttributionWindow::run($shop))
            ->map(fn (Collection $group, $window) => [
                'window'   => (int) $window,
                'shop_ids' => $group->pluck('id')->all(),
            ])
            ->values();
    }

    /**
     * @param Collection<int, Shop> $shops
     */
    protected function revenueByType(Collection $shops, ?Carbon $from, ?Carbon $to, string $revenueColumn, string $groupBy = 'ts.type', ?string $onlyType = null): Collection
    {
        $totals = collect();

        foreach ($this->shopsByWindow($shops) as $group) {
            DB::table('invoices')
                ->join('model_has_traffic_sources as p', function ($join) use ($group) {
                    $join->on('p.model_id', '=', 'invoices.customer_id')
                        ->where('p.model_type', '=', 'Customer');

                    $this->constrainToAttributionWindow($join, $group['window']);
                })
                ->join('traffic_sources as ts', 'ts.id', '=', 'p.traffic_source_id')
                ->whereIn('invoices.shop_id', $group['shop_ids'])
                ->where('invoices.in_process', false)
                ->when($onlyType, fn ($query) => $query->where('ts.type', $onlyType))
                ->when($from, fn ($query) => $query->where('invoices.date', '>=', $from))
                ->when($to, fn ($query) => $query->where('invoices.date', '<=', $to))
                ->groupBy($groupBy)
                ->select(DB::raw($groupBy.' as bucket'), DB::raw("SUM(invoices.{$revenueColumn} * p.share) as amount"))
                ->get()
                ->each(fn ($row) => $totals[$row->bucket] = ($totals[$row->bucket] ?? 0) + (float) $row->amount);
        }

        return $totals;
    }

    /**
     * @param Collection<int, Shop> $shops
     */
    protected function registrationsByType(Collection $shops, ?Carbon $from, ?Carbon $to, string $groupBy = 'ts.type', ?string $onlyType = null): Collection
    {
        $totals = collect();

        foreach ($this->shopsByWindow($shops) as $group) {
            DB::table('customers')
                ->join('model_has_traffic_sources as p', function ($join) use ($group) {
                    $join->on('p.model_id', '=', 'customers.id')
                        ->where('p.model_type', '=', 'Customer');

                    $this->constrainToTouchWindow($join, 'customers.created_at', $group['window']);
                })
                ->join('traffic_sources as ts', 'ts.id', '=', 'p.traffic_source_id')
                ->whereIn('customers.shop_id', $group['shop_ids'])
                ->when($onlyType, fn ($query) => $query->where('ts.type', $onlyType))
                ->when($from, fn ($query) => $query->where('customers.created_at', '>=', $from))
                ->when($to, fn ($query) => $query->where('customers.created_at', '<=', $to))
                ->groupBy($groupBy)
                ->select(DB::raw($groupBy.' as bucket'), DB::raw('SUM(p.share) as registrations'))
                ->get()
                ->each(fn ($row) => $totals[$row->bucket] = ($totals[$row->bucket] ?? 0) + (float) $row->registrations);
        }

        return $totals;
    }

    /**
     * @param Collection<int, Shop> $shops
     */
    protected function ordersByType(Collection $shops, ?Carbon $from, ?Carbon $to, string $groupBy = 'ts.type', ?string $onlyType = null): Collection
    {
        $totals = collect();

        foreach ($this->shopsByWindow($shops) as $group) {
            DB::table('orders')
                ->join('model_has_traffic_sources as p', function ($join) use ($group) {
                    $join->on('p.model_id', '=', 'orders.customer_id')
                        ->where('p.model_type', '=', 'Customer');

                    $this->constrainToTouchWindow($join, 'orders.date', $group['window']);
                })
                ->join('traffic_sources as ts', 'ts.id', '=', 'p.traffic_source_id')
                ->whereIn('orders.shop_id', $group['shop_ids'])
                ->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
                ->whereNull('orders.deleted_at')
                ->when($onlyType, fn ($query) => $query->where('ts.type', $onlyType))
                ->when($from, fn ($query) => $query->where('orders.date', '>=', $from))
                ->when($to, fn ($query) => $query->where('orders.date', '<=', $to))
                ->groupBy($groupBy)
                ->select(DB::raw($groupBy.' as bucket'), DB::raw('SUM(p.share) as orders'))
                ->get()
                ->each(fn ($row) => $totals[$row->bucket] = ($totals[$row->bucket] ?? 0) + (float) $row->orders);
        }

        return $totals;
    }

    /**
     * The leading indicator: value of orders placed but not yet invoiced, in the parent's currency.
     * Invoicing runs a day or two behind, and this is what a mailshot sent this morning shows today.
     *
     * @param Collection<int, Shop> $shops
     */
    protected function pendingRevenueByType(Collection $shops, ?Carbon $from, ?Carbon $to, string $amountColumn, string $groupBy = 'ts.type', ?string $onlyType = null): Collection
    {
        $totals = collect();

        foreach ($this->shopsByWindow($shops) as $group) {
            DB::table('orders')
                ->join('model_has_traffic_sources as p', function ($join) use ($group) {
                    $join->on('p.model_id', '=', 'orders.customer_id')
                        ->where('p.model_type', '=', 'Customer');

                    $this->constrainToTouchWindow($join, 'orders.date', $group['window']);
                })
                ->join('traffic_sources as ts', 'ts.id', '=', 'p.traffic_source_id')
                ->whereIn('orders.shop_id', $group['shop_ids'])
                ->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
                ->whereNull('orders.deleted_at')
                ->whereNotExists(fn ($invoice) => $invoice
                    ->select(DB::raw(1))
                    ->from('invoices')
                    ->whereColumn('invoices.order_id', 'orders.id')
                    ->where('invoices.in_process', false))
                ->when($onlyType, fn ($query) => $query->where('ts.type', $onlyType))
                ->when($from, fn ($query) => $query->where('orders.date', '>=', $from))
                ->when($to, fn ($query) => $query->where('orders.date', '<=', $to))
                ->groupBy($groupBy)
                ->select(DB::raw($groupBy.' as bucket'), DB::raw("SUM(orders.{$amountColumn} * p.share) as amount"))
                ->get()
                ->each(fn ($row) => $totals[$row->bucket] = ($totals[$row->bucket] ?? 0) + (float) $row->amount);
        }

        return $totals;
    }

    /**
     * People each channel actually sent us, converted or not - the ones attribution never sees,
     * because they never logged in or registered.
     *
     * @param Collection<int, Shop> $shops
     */
    protected function visitsByType(Collection $shops, ?Carbon $from, ?Carbon $to, string $groupBy = 'ts.type', ?string $onlyType = null): Collection
    {
        return DB::table('traffic_source_visits as v')
            ->join('traffic_sources as ts', 'ts.id', '=', 'v.traffic_source_id')
            ->whereIn('v.shop_id', $shops->pluck('id'))
            ->when($onlyType, fn ($query) => $query->where('ts.type', $onlyType))
            ->when($from, fn ($query) => $query->where('v.date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->where('v.date', '<=', $to->toDateString()))
            ->groupBy($groupBy)
            ->select(DB::raw($groupBy.' as bucket'), DB::raw('SUM(v.visits) as visits'))
            ->pluck('visits', 'bucket');
    }

    /**
     * @param Collection<int, Shop> $shops
     */
    protected function spendByType(Collection $shops, ?Carbon $from, ?Carbon $to, string $costColumn, string $groupBy = 'ts.type', ?string $onlyType = null): Collection
    {
        return DB::table('traffic_source_costs as c')
            ->join('traffic_sources as ts', 'ts.id', '=', 'c.traffic_source_id')
            ->whereIn('c.shop_id', $shops->pluck('id'))
            ->when($onlyType, fn ($query) => $query->where('ts.type', $onlyType))
            ->when($from, fn ($query) => $query->where('c.date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->where('c.date', '<=', $to->toDateString()))
            ->groupBy($groupBy)
            ->select(DB::raw($groupBy.' as bucket'), DB::raw("SUM(c.{$costColumn}) as spend"))
            ->pluck('spend', 'bucket')
            ->map(fn ($amount) => (float) $amount);
    }
}
