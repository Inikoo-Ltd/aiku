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
     * A view cannot take a date range as an argument, so the existing marketing_channel_performance
     * can only ever answer all-time questions. This one is at (shop, channel, day) grain instead:
     * management filters on `date` and aggregates whatever window they actually care about.
     *
     * Revenue is share-weighted invoice net, matching the dashboard exactly, so a SUM over all days
     * reproduces marketing_channel_performance and any sub-window is an honest slice of it.
     */
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW marketing_channel_daily AS
            WITH spend AS (
                SELECT shop_id, traffic_source_id, date::date AS date, SUM(amount) AS cost
                FROM traffic_source_costs
                GROUP BY 1, 2, 3
            ),
            revenue AS (
                SELECT i.shop_id,
                       p.traffic_source_id,
                       i.date::date                        AS date,
                       SUM(i.net_amount * p.share)         AS revenue,
                       SUM(p.share)                        AS invoices
                FROM invoices i
                JOIN model_has_traffic_sources p
                  ON p.model_id = i.customer_id AND p.model_type = 'Customer'
                WHERE i.in_process = false AND i.date IS NOT NULL
                GROUP BY 1, 2, 3
            ),
            registrations AS (
                SELECT c.shop_id,
                       p.traffic_source_id,
                       c.created_at::date                  AS date,
                       SUM(p.share)                        AS registrations
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
            SELECT
                g.date,
                g.shop_id,
                s.slug                                     AS shop,
                ts.name                                    AS channel,
                ts.type,
                COALESCE(sp.cost, 0)                       AS cost,
                COALESCE(rv.revenue, 0)                    AS revenue,
                COALESCE(rv.invoices, 0)                   AS invoices,
                COALESCE(rg.registrations, 0)              AS registrations
            FROM grid g
            JOIN shops s ON s.id = g.shop_id
            JOIN traffic_sources ts ON ts.id = g.traffic_source_id
            LEFT JOIN spend sp
                   ON sp.shop_id = g.shop_id AND sp.traffic_source_id = g.traffic_source_id AND sp.date = g.date
            LEFT JOIN revenue rv
                   ON rv.shop_id = g.shop_id AND rv.traffic_source_id = g.traffic_source_id AND rv.date = g.date
            LEFT JOIN registrations rg
                   ON rg.shop_id = g.shop_id AND rg.traffic_source_id = g.traffic_source_id AND rg.date = g.date
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS marketing_channel_daily');
    }
};
