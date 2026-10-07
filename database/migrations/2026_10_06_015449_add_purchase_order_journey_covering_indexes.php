<?php

/*
 * author Louis Perez
 * created on 06-10-2026
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS products_id_journey_index ON products (id) INCLUDE (organisation_id, state, is_for_sale, status, webpage_id)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS phos_org_stock_id_product_id_index ON product_has_org_stocks (org_stock_id, product_id)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS webpages_id_journey_index ON webpages (id) INCLUDE (state, live_at, created_at)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS stock_delivery_items_org_stock_id_stock_delivery_id_index ON stock_delivery_items (org_stock_id, stock_delivery_id)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS stock_deliveries_id_journey_index ON stock_deliveries (id) INCLUDE (received_at, state)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS stock_deliveries_id_journey_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS stock_delivery_items_org_stock_id_stock_delivery_id_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS webpages_id_journey_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS phos_org_stock_id_product_id_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_id_journey_index');
    }
};
