<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Nov 2025 10:53:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * PostgresSQL cannot create/drop indexes CONCURRENTLY inside a transaction.
     * Disable the migration transaction so we can use CONCURRENTLY safely.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('portfolios')) {
            return;
        }

        $indexName = 'portfolios_platform_shop_created_at_idx';

        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                // Use CONCURRENTLY for large tables to avoid long locks
                $sql = "CREATE INDEX CONCURRENTLY IF NOT EXISTS $indexName ON portfolios (platform_id, shop_id, created_at)";
                DB::statement($sql);
            } else {
                // Fallback: use Schema builder (may error if index exists but covers non-PG envs)
                Schema::table('portfolios', function ($table) use ($indexName) {
                    /* @var Illuminate\Database\Schema\Blueprint $table */
                    $table->index(['platform_id', 'shop_id', 'created_at'], $indexName);
                });
            }
        } catch (\Throwable) {
            // Swallow to keep migration idempotent across environments
            // logger()->warning('Creating portfolio index failed', ['index' => $indexName, 'error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('portfolios')) {
            return;
        }

        $indexName = 'portfolios_platform_shop_created_at_idx';
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement("DROP INDEX CONCURRENTLY IF EXISTS $indexName");
            } else {
                Schema::table('portfolios', function ($table) use ($indexName) {
                    /* @var Illuminate\Database\Schema\Blueprint $table */
                    $table->dropIndex($indexName);
                });
            }
        } catch (\Throwable) {
            // Ignore failures when dropping an index
        }
    }
};
