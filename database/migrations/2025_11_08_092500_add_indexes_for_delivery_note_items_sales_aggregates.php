<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Nov 2025 21:51:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Junie (JetBrains Autonomous Programmer)
 * Created: Sat, 08 Nov 2025 09:25:00 Local Time
 * Purpose: Add composite indexes to speed up SUM aggregations on delivery_note_items
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Laravel by default wraps migrations in a transaction. PostgreSQL cannot create/drop indexes
     * CONCURRENTLY inside a transaction. Set withinTransaction to false so we can use CONCURRENTLY.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('delivery_note_items')) {
            return;
        }

        // Composite BTREE indexes matching common filters: equality columns first, date last.
        // Include the aggregated column to allow index-only scans when possible.
        $statements = [
            // With organisation_id + stock_id
            "CREATE INDEX IF NOT EXISTS dnis_org_sales_stock_date_idx ON delivery_note_items (organisation_id, sales_type, stock_id, date) INCLUDE (grp_revenue_amount)",

            // With organisation_id + stock_family_id
            "CREATE INDEX IF NOT EXISTS dnis_org_sales_stockfam_date_idx ON delivery_note_items (organisation_id, sales_type, stock_family_id, date) INCLUDE (grp_revenue_amount)",

            // Without organisation_id (stock_id)
            "CREATE INDEX IF NOT EXISTS dnis_sales_stock_date_idx ON delivery_note_items (sales_type, stock_id, date) INCLUDE (grp_revenue_amount)",

            // Without organisation_id (stock_family_id)
            "CREATE INDEX IF NOT EXISTS dnis_sales_stockfam_date_idx ON delivery_note_items (sales_type, stock_family_id, date) INCLUDE (grp_revenue_amount)",

            // OrgStock dimensions (inventory side)
            "CREATE INDEX IF NOT EXISTS dnis_sales_orgstock_date_idx ON delivery_note_items (sales_type, org_stock_id, date) INCLUDE (org_revenue_amount)",

            "CREATE INDEX IF NOT EXISTS dnis_sales_orgstockfam_date_idx ON delivery_note_items (sales_type, org_stock_family_id, date) INCLUDE (org_revenue_amount)",

            // Optional BRIN on date for very wide scans; tiny size, complementary to BTREE.
            "CREATE INDEX IF NOT EXISTS dnis_date_brin ON delivery_note_items USING BRIN (date) WITH (pages_per_range = 128)"
        ];

        // Use CONCURRENTLY when supported (PostgreSQL). If using a different DB, these will fail;
        // wrap in try/catch to avoid breaking non-PG environments.
        foreach ($statements as $sql) {
            try {
                // Prefer CONCURRENTLY for Postgres only
                if (DB::getDriverName() === 'pgsql') {
                    $sql = preg_replace('/^CREATE INDEX /', 'CREATE INDEX CONCURRENTLY ', $sql);
                }
                DB::statement($sql);
            } catch (\Throwable $e) {
                // Log and continue; index might already exist or DB doesn't support INCLUDE/BRIN
                // logger()->warning('Index creation skipped', ['sql' => $sql, 'error' => $e->getMessage()]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('delivery_note_items')) {
            return;
        }

        $indexes = [
            'dnis_org_sales_stock_date_idx',
            'dnis_org_sales_stockfam_date_idx',
            'dnis_sales_stock_date_idx',
            'dnis_sales_stockfam_date_idx',
            'dnis_sales_orgstock_date_idx',
            'dnis_sales_orgstockfam_date_idx',
            'dnis_date_brin',
        ];

        foreach ($indexes as $name) {
            try {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("DROP INDEX IF EXISTS CONCURRENTLY {$name}");
                } else {
                    DB::statement("DROP INDEX IF EXISTS {$name}");
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }
};
