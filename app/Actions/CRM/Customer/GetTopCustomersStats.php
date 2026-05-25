<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTopCustomersStats
{
    use AsObject;

    public function handle(Group|Organisation|Shop $parent, $fromDate = null, $toDate = null, int $limit = 10): array
    {
        $limit = $this->normaliseLimit($limit);

        $timeSeriesIds = $this->getTimeSeriesIds($parent);

        if (empty($timeSeriesIds)) {
            return [];
        }

        $aggregateQuery = DB::table('customer_time_series_records')
            ->select('customer_time_series_id')
            ->selectRaw('SUM(sales) as sales_total')
            ->selectRaw('SUM(sales_org_currency) as sales_org_currency_total')
            ->selectRaw('SUM(sales_grp_currency) as sales_grp_currency_total')
            ->selectRaw('SUM(invoices) as invoices_total')
            ->whereIn('customer_time_series_id', array_keys($timeSeriesIds))
            ->where('frequency', 'D')
            ->groupBy('customer_time_series_id');

        if ($fromDate) {
            $aggregateQuery->where('from', '>=', Carbon::parse((string) $fromDate)->startOfDay());
        }

        if ($toDate) {
            $aggregateQuery->where('from', '<=', Carbon::parse((string) $toDate)->endOfDay());
        }

        $aggregates = $aggregateQuery->get()
            ->keyBy('customer_time_series_id');

        if ($aggregates->isEmpty()) {
            return [];
        }

        $sortField = match (true) {
            $parent instanceof Shop         => 'sales_total',
            $parent instanceof Organisation => 'sales_org_currency_total',
            default                         => 'sales_grp_currency_total',
        };

        $orderedTimeSeriesIds = $aggregates
            ->sortByDesc(fn ($row) => (float) ($row->{$sortField} ?? 0))
            ->take($limit)
            ->keys()
            ->all();

        if (empty($orderedTimeSeriesIds)) {
            return [];
        }

        $customerIds = collect($orderedTimeSeriesIds)
            ->map(fn ($timeSeriesId) => $timeSeriesIds[$timeSeriesId])
            ->all();

        $customers = Customer::query()
            ->select(['id', 'slug', 'reference', 'name', 'contact_name', 'company_name', 'shop_id', 'organisation_id', 'group_id', 'last_invoiced_at'])
            ->whereIn('id', $customerIds)
            ->with([
                'shop'                  => fn ($q) => $q->select(['id', 'slug', 'currency_id']),
                'shop.currency'         => fn ($q) => $q->select(['id', 'code']),
                'organisation'          => fn ($q) => $q->select(['id', 'slug', 'code', 'currency_id']),
                'organisation.currency' => fn ($q) => $q->select(['id', 'code']),
                'group'                 => fn ($q) => $q->select(['id', 'slug', 'currency_id']),
                'group.currency'        => fn ($q) => $q->select(['id', 'code']),
            ])
            ->get()
            ->keyBy('id');

        $parentType = match (true) {
            $parent instanceof Shop         => 'Shop',
            $parent instanceof Organisation => 'Organisation',
            default                         => 'Group',
        };

        $results = [];

        foreach ($orderedTimeSeriesIds as $timeSeriesId) {
            $customerId = $timeSeriesIds[$timeSeriesId] ?? null;
            $customer   = $customerId ? $customers->get($customerId) : null;

            if (!$customer) {
                continue;
            }

            $aggregate = $aggregates->get($timeSeriesId);

            $results[] = [
                'id'                         => $customer->id,
                'slug'                       => $customer->slug,
                'reference'                  => $customer->reference,
                'name'                       => $customer->name ?: $customer->contact_name ?: $customer->company_name,
                'last_invoiced_at'           => $customer->last_invoiced_at?->toDateString(),
                'sales'                      => (float) ($aggregate->sales_total ?? 0),
                'sales_org_currency'         => (float) ($aggregate->sales_org_currency_total ?? 0),
                'sales_grp_currency'         => (float) ($aggregate->sales_grp_currency_total ?? 0),
                'invoices'                   => (int) ($aggregate->invoices_total ?? 0),
                'shop_slug'                  => $customer->shop?->slug,
                'organisation_slug'          => $customer->organisation?->slug,
                'shop_currency_code'         => $customer->shop?->currency?->code,
                'organisation_currency_code' => $customer->organisation?->currency?->code,
                'group_currency_code'        => $customer->group?->currency?->code,
                'parent_type'                => $parentType,
            ];
        }

        return $results;
    }

    protected function normaliseLimit(int $limit): int
    {
        if (!in_array($limit, [3, 10, 50], true)) {
            return 10;
        }

        return $limit;
    }

    protected function getTimeSeriesIds(Group|Organisation|Shop $parent): array
    {
        $query = DB::table('customer_time_series')
            ->join('customers', 'customers.id', '=', 'customer_time_series.customer_id')
            ->select('customer_time_series.id', 'customer_time_series.customer_id')
            ->where('customer_time_series.frequency', 'daily')
            ->whereNull('customers.deleted_at');

        if ($parent instanceof Shop) {
            $query->where('customers.shop_id', $parent->id);
        } elseif ($parent instanceof Organisation) {
            $query->where('customers.organisation_id', $parent->id);
        } else {
            $query->where('customers.group_id', $parent->id);
        }

        return $query->pluck('customer_id', 'id')->all();
    }
}
