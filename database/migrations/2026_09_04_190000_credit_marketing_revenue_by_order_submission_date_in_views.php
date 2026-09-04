<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Mirrors WithAttributionWindow::ATTRIBUTABLE_DATE: the order counts from when it was submitted,
     * not from when the basket was opened, so a touch that led to a checkout of an old basket is
     * credited.
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
                        COALESCE(o.submitted_at, o.date, i.date) >= p.first_touch_at
                    AND (
                          COALESCE((s.settings->'marketing'->>'attribution_window_days')::int, 90) <= 0
                       OR COALESCE(o.submitted_at, o.date, i.date) <= p.last_touch_at
                              + (COALESCE((s.settings->'marketing'->>'attribution_window_days')::int, 90) || ' days')::interval
                    )
                    )
              )
        ");
    }

    public function down(): void
    {
    }
};
