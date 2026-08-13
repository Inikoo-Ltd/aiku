<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 13 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateRfm;
use App\Enums\CRM\Customer\CustomerRfmSegmentEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Reproduces the classification of CustomerHydrateRfm for a shop as it stood on any past date,
 * so snapshot history can be rebuilt from the invoices instead of from the tags a customer
 * happens to carry today.
 */
class CalculateCustomerRfmSegmentation
{
    use AsObject;

    public function handle(int $shopId, Carbon $asOf): array
    {
        $periodEnd   = $asOf->copy()->endOfDay();
        $periodStart = $periodEnd->copy()->subYear();

        $recencyCutoffs = CustomerHydrateRfm::make()->recencyCutoffs($periodEnd);

        $rows = DB::select(
            "
            WITH invoice_stats AS (
                SELECT c.id AS customer_id,
                       MIN(i.date) AS first_invoice_date,
                       MAX(i.date) FILTER (WHERE i.date >= :window_start) AS last_invoice_date,
                       COUNT(*) FILTER (WHERE i.date >= :window_start_count) AS frequency_count,
                       COALESCE(SUM(i.net_amount) FILTER (WHERE i.date >= :window_start_spend), 0) AS monetary_value
                FROM customers c
                JOIN invoices i ON i.customer_id = c.id
                WHERE c.shop_id = :shop_id
                  AND c.deleted_at IS NULL
                  AND i.deleted_at IS NULL
                  AND i.in_process = false
                  AND i.date IS NOT NULL
                  AND i.date <= :period_end
                GROUP BY c.id
            ), scoped AS (
                SELECT * FROM invoice_stats WHERE frequency_count > 0
            ), benchmark AS (
                SELECT percentile_cont(0.5)  WITHIN GROUP (ORDER BY monetary_value) AS p50,
                       percentile_cont(0.8)  WITHIN GROUP (ORDER BY monetary_value) AS p80,
                       percentile_cont(0.95) WITHIN GROUP (ORDER BY monetary_value) AS p95,
                       percentile_cont(0.99) WITHIN GROUP (ORDER BY monetary_value) AS p99,
                       (SELECT monetary_value FROM scoped ORDER BY monetary_value DESC LIMIT 1 OFFSET :top_offset) AS top_spender_floor
                FROM scoped
            ), classified AS (
                SELECT
                    CASE
                        WHEN s.first_invoice_date >= :new_customer_cutoff THEN :new_customer
                        WHEN s.last_invoice_date  >= :active_cutoff       THEN :active
                        WHEN s.last_invoice_date  >= :at_risk_cutoff      THEN :at_risk
                        WHEN s.last_invoice_date  >= :inactive_cutoff     THEN :inactive
                        ELSE :lost_customer
                    END AS recency,
                    CASE
                        WHEN s.frequency_count <= 1                  THEN :one_time_buyer
                        WHEN s.frequency_count <= :occasional_max    THEN :occasional_shopper
                        WHEN s.frequency_count <= :frequent_max      THEN :frequent_buyer
                        ELSE :brand_advocate
                    END AS frequency,
                    CASE
                        WHEN b.top_spender_floor IS NOT NULL AND s.monetary_value >= b.top_spender_floor THEN :top_10
                        WHEN s.monetary_value <= b.p50 THEN :low_value
                        WHEN s.monetary_value <= b.p80 THEN :medium_value
                        WHEN s.monetary_value <= b.p95 THEN :high_value
                        WHEN s.monetary_value <= b.p99 THEN :gold_reward
                        ELSE :top_100
                    END AS monetary
                FROM scoped s, benchmark b
            )
            SELECT recency AS segment, count(*) AS total FROM classified GROUP BY recency
            UNION ALL
            SELECT frequency, count(*) FROM classified GROUP BY frequency
            UNION ALL
            SELECT monetary, count(*) FROM classified GROUP BY monetary
        ",
            [
                'shop_id'             => $shopId,
                'window_start'        => $periodStart,
                'window_start_count'  => $periodStart,
                'window_start_spend'  => $periodStart,
                'period_end'          => $periodEnd,
                'top_offset'          => CustomerRfmSegmentEnum::TOP_SPENDERS_SIZE - 1,
                'new_customer_cutoff' => $recencyCutoffs[CustomerRfmSegmentEnum::RECENT_DAYS],
                'active_cutoff'       => $recencyCutoffs[CustomerRfmSegmentEnum::RECENT_DAYS],
                'at_risk_cutoff'      => $recencyCutoffs[CustomerRfmSegmentEnum::AT_RISK_DAYS],
                'inactive_cutoff'     => $recencyCutoffs[CustomerRfmSegmentEnum::INACTIVE_DAYS],
                'occasional_max'      => CustomerRfmSegmentEnum::OCCASIONAL_SHOPPER_MAX_INVOICES,
                'frequent_max'        => CustomerRfmSegmentEnum::FREQUENT_BUYER_MAX_INVOICES,
                'new_customer'        => CustomerRfmSegmentEnum::NEW_CUSTOMER->tagName(),
                'active'              => CustomerRfmSegmentEnum::ACTIVE->tagName(),
                'at_risk'             => CustomerRfmSegmentEnum::AT_RISK->tagName(),
                'inactive'            => CustomerRfmSegmentEnum::INACTIVE->tagName(),
                'lost_customer'       => CustomerRfmSegmentEnum::LOST_CUSTOMER->tagName(),
                'one_time_buyer'      => CustomerRfmSegmentEnum::ONE_TIME_BUYER->tagName(),
                'occasional_shopper'  => CustomerRfmSegmentEnum::OCCASIONAL_SHOPPER->tagName(),
                'frequent_buyer'      => CustomerRfmSegmentEnum::FREQUENT_BUYER->tagName(),
                'brand_advocate'      => CustomerRfmSegmentEnum::BRAND_ADVOCATE->tagName(),
                'low_value'           => CustomerRfmSegmentEnum::LOW_VALUE->tagName(),
                'medium_value'        => CustomerRfmSegmentEnum::MEDIUM_VALUE->tagName(),
                'high_value'          => CustomerRfmSegmentEnum::HIGH_VALUE->tagName(),
                'gold_reward'         => CustomerRfmSegmentEnum::GOLD_REWARD->tagName(),
                'top_100'             => CustomerRfmSegmentEnum::TOP_100->tagName(),
                'top_10'              => CustomerRfmSegmentEnum::TOP_10->tagName(),
            ]
        );

        $summary = [];
        foreach ($rows as $row) {
            $summary[$row->segment] = (int) $row->total;
        }

        return $summary;
    }
}
