<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Procurement\OrgPartner;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerSupplyDurations
{
    use AsObject;

    public const int MIN_SAMPLES = 5;

    public const array DEFAULT_DAYS = [
        'pick_wait'  => 7.0,
        'production' => 7.0,
        'dispatch'   => 1.0,
        'transit'    => 3.0,
    ];

    /**
     * @return array{pick_wait: float, production: float, dispatch: float, transit: float}
     */
    public function handle(OrgPartner $orgPartner): array
    {
        return Cache::remember(
            "partner_supply_durations:$orgPartner->id",
            now()->addHours(6),
            fn () => $this->measure($orgPartner)
        );
    }

    private function measure(OrgPartner $orgPartner): array
    {
        $sellerId = $orgPartner->partner_id;
        $since    = now()->subYear();

        $measured = [
            'pick_wait'  => $this->median(
                DB::table('partner_shopping_list_items')
                    ->join('transactions', 'transactions.id', 'partner_shopping_list_items.transaction_id')
                    ->where('partner_shopping_list_items.partner_organisation_id', $sellerId)
                    ->where('partner_shopping_list_items.created_at', '>=', $since),
                'transactions.created_at - partner_shopping_list_items.created_at'
            ),
            'production' => $this->median(
                DB::table('job_orders')
                    ->where('organisation_id', $sellerId)
                    ->whereNull('deleted_at')
                    ->whereNotNull('received_at')
                    ->where('created_at', '>=', $since),
                'job_orders.received_at - coalesce(job_orders.confirmed_at, job_orders.created_at)'
            ),
            'dispatch'   => $this->median(
                DB::table('partner_shopping_list_items')
                    ->join('transactions', 'transactions.id', 'partner_shopping_list_items.transaction_id')
                    ->join('orders', 'orders.id', 'transactions.order_id')
                    ->where('partner_shopping_list_items.partner_organisation_id', $sellerId)
                    ->whereNotNull('orders.dispatched_at')
                    ->where('orders.dispatched_at', '>=', $since),
                'orders.dispatched_at - transactions.created_at'
            ),
            'transit'    => $this->median(
                DB::table('stock_deliveries')
                    ->where('parent_type', 'OrgPartner')
                    ->where('parent_id', $orgPartner->id)
                    ->whereNull('deleted_at')
                    ->whereNotNull('dispatched_at')
                    ->whereNotNull('received_at')
                    ->where('received_at', '>=', $since),
                'stock_deliveries.received_at - stock_deliveries.dispatched_at'
            ),
        ];

        foreach ($measured as $leg => $days) {
            $measured[$leg] = $days ?? self::DEFAULT_DAYS[$leg];
        }

        return $measured;
    }

    private function median(Builder $query, string $interval): ?float
    {
        $row = $query->selectRaw(
            "count(*) as samples, percentile_cont(0.5) within group (order by extract(epoch from ($interval)) / 86400) as days"
        )->first();

        if ((int) $row->samples < self::MIN_SAMPLES) {
            return null;
        }

        return max(0.0, round((float) $row->days, 2));
    }
}
