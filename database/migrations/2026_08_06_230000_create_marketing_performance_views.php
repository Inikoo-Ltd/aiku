<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Read-only views for ad-hoc and AI-assisted SQL over marketing performance. The underlying
     * pivot is polymorphic and share-weighted, and mailshot attribution hangs off a campaign
     * reference convention; both are easy to query wrongly (full-revenue joins double-count across
     * channels). These views bake the correct semantics in once, so a plain SELECT gives honest
     * numbers.
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW marketing_channel_performance AS
            SELECT
                ts.id                                            AS traffic_source_id,
                ts.shop_id,
                s.slug                                           AS shop,
                ts.name                                          AS channel,
                ts.type,
                COALESCE(st.number_customers, 0)                 AS registrations,
                COALESCE(st.number_customer_purchases, 0)        AS orders,
                COALESCE(st.total_customer_revenue, 0)           AS revenue,
                COALESCE(st.total_cost, 0)                       AS cost,
                CASE WHEN COALESCE(st.total_cost, 0) > 0
                    THEN ROUND(st.total_customer_revenue / st.total_cost, 2)
                END                                              AS roas,
                CASE WHEN COALESCE(st.total_cost, 0) > 0 AND COALESCE(st.number_customers, 0) > 0
                    THEN ROUND(st.total_cost / st.number_customers, 2)
                END                                              AS cost_per_registration
            FROM traffic_sources ts
            JOIN shops s ON s.id = ts.shop_id
            LEFT JOIN traffic_source_stats st ON st.traffic_source_id = ts.id
        ");

        DB::statement("
            CREATE OR REPLACE VIEW marketing_mailshot_performance AS
            SELECT
                m.id                                                          AS mailshot_id,
                m.shop_id,
                s.slug                                                        AS shop,
                m.subject,
                m.type,
                m.sent_at,
                COALESCE(ms.number_dispatched_emails, 0)                      AS sent,
                COALESCE(ms.number_dispatched_emails_state_opened, 0)
                    + COALESCE(ms.number_dispatched_emails_state_clicked, 0)  AS opened,
                COALESCE(ms.number_dispatched_emails_state_clicked, 0)        AS clicked,
                COALESCE(ms.number_dispatched_emails_state_unsubscribed, 0)   AS unsubscribed,
                COALESCE(attr.customers, 0)                                   AS attributed_customers,
                COALESCE(attr.revenue, 0)                                     AS attributed_revenue,
                COALESCE(reg.registered, 0)                                   AS prospects_registered
            FROM mailshots m
            JOIN shops s ON s.id = m.shop_id
            LEFT JOIN mailshot_stats ms ON ms.mailshot_id = m.id
            LEFT JOIN traffic_source_campaigns c ON c.reference = 'mailshot-' || m.id::text
            LEFT JOIN LATERAL (
                SELECT SUM(p.share)                    AS customers,
                       SUM(p.share * cs.sales_all)     AS revenue
                FROM model_has_traffic_sources p
                JOIN customer_stats cs ON cs.customer_id = p.model_id
                WHERE p.model_type = 'Customer'
                  AND p.traffic_source_campaign_id = c.id
            ) attr ON true
            LEFT JOIN LATERAL (
                SELECT COUNT(*) AS registered
                FROM model_has_traffic_sources p
                JOIN prospects pr ON pr.id = p.model_id
                WHERE p.model_type = 'Prospect'
                  AND p.traffic_source_campaign_id = c.id
                  AND pr.customer_id IS NOT NULL
            ) reg ON true
            WHERE m.type IN ('newsletter', 'marketing', 'invite')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS marketing_mailshot_performance');
        DB::statement('DROP VIEW IF EXISTS marketing_channel_performance');
    }
};
