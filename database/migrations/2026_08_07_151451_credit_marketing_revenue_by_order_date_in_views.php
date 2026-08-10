<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Recreates the marketing views so revenue is credited by the date the customer *ordered*, not by
     * the date the invoice was raised - matching WithAttributionWindow::ATTRIBUTABLE_DATE, which the
     * PHP side uses. Invoicing lags orders by a day or two, and a touch landing inside that lag was
     * collecting orders already placed: 93% of all attributed revenue in prod when this was found.
     *
     * The whole view stack is recreated because marketing_attributable_invoices feeds the others.
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW marketing_attributable_invoices AS
            SELECT i.id            AS invoice_id,
                   i.shop_id,
                   i.date::date    AS date,
                   i.net_amount,
                   p.traffic_source_id,
                   p.traffic_source_campaign_id,
                   p.share
            FROM invoices i
            JOIN model_has_traffic_sources p
              ON p.model_id = i.customer_id AND p.model_type = 'Customer'
            JOIN shops s ON s.id = i.shop_id
            LEFT JOIN orders o ON o.id = i.order_id
            WHERE i.in_process = false
              AND i.date IS NOT NULL
              AND (
                    p.first_touch_at IS NULL
                 OR (
                        COALESCE(o.date, i.date) >= p.first_touch_at
                    AND (
                          COALESCE((s.settings->'marketing'->>'attribution_window_days')::int, 90) <= 0
                       OR COALESCE(o.date, i.date) <= p.last_touch_at
                              + (COALESCE((s.settings->'marketing'->>'attribution_window_days')::int, 90) || ' days')::interval
                    )
                    )
              )
        ");

        DB::statement("
            CREATE OR REPLACE VIEW marketing_channel_daily AS
            WITH spend AS (
                SELECT shop_id, traffic_source_id, date::date AS date, SUM(amount) AS cost
                FROM traffic_source_costs GROUP BY 1, 2, 3
            ),
            revenue AS (
                SELECT shop_id, traffic_source_id, date,
                       SUM(net_amount * share) AS revenue,
                       SUM(share)              AS invoices
                FROM marketing_attributable_invoices GROUP BY 1, 2, 3
            ),
            registrations AS (
                SELECT c.shop_id, p.traffic_source_id, c.created_at::date AS date,
                       SUM(p.share) AS registrations
                FROM customers c
                JOIN model_has_traffic_sources p
                  ON p.model_id = c.id AND p.model_type = 'Customer'
                GROUP BY 1, 2, 3
            ),
            grid AS (
                SELECT shop_id, traffic_source_id, date FROM spend
                UNION SELECT shop_id, traffic_source_id, date FROM revenue
                UNION SELECT shop_id, traffic_source_id, date FROM registrations
            )
            SELECT g.date, g.shop_id, s.slug AS shop, ts.name AS channel, ts.type,
                   COALESCE(sp.cost, 0)          AS cost,
                   COALESCE(rv.revenue, 0)       AS revenue,
                   COALESCE(rv.invoices, 0)      AS invoices,
                   COALESCE(rg.registrations, 0) AS registrations
            FROM grid g
            JOIN shops s ON s.id = g.shop_id
            JOIN traffic_sources ts ON ts.id = g.traffic_source_id
            LEFT JOIN spend sp ON sp.shop_id = g.shop_id AND sp.traffic_source_id = g.traffic_source_id AND sp.date = g.date
            LEFT JOIN revenue rv ON rv.shop_id = g.shop_id AND rv.traffic_source_id = g.traffic_source_id AND rv.date = g.date
            LEFT JOIN registrations rg ON rg.shop_id = g.shop_id AND rg.traffic_source_id = g.traffic_source_id AND rg.date = g.date
        ");

        DB::statement("
            CREATE OR REPLACE VIEW marketing_channel_performance AS
            SELECT ts.id AS traffic_source_id, ts.shop_id, s.slug AS shop,
                   ts.name AS channel, ts.type,
                   COALESCE(st.number_customers, 0)          AS registrations,
                   COALESCE(st.number_customer_purchases, 0)  AS orders,
                   COALESCE(st.total_customer_revenue, 0)     AS revenue,
                   COALESCE(st.total_cost, 0)                 AS cost,
                   CASE WHEN COALESCE(st.total_cost, 0) > 0
                        THEN ROUND(st.total_customer_revenue / st.total_cost, 2) END AS roas,
                   CASE WHEN COALESCE(st.total_cost, 0) > 0 AND COALESCE(st.number_customers, 0) > 0
                        THEN ROUND(st.total_cost / st.number_customers, 2) END AS cost_per_registration
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
                SELECT SUM(ai.share)                   AS customers,
                       SUM(ai.net_amount * ai.share)   AS revenue
                FROM marketing_attributable_invoices ai
                WHERE ai.traffic_source_campaign_id = c.id
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
        DB::statement('DROP VIEW IF EXISTS marketing_channel_daily');
        DB::statement('DROP VIEW IF EXISTS marketing_attributable_invoices');
    }
};
