<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 Oct 2025 20:27:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the unique constraint on webpages(canonical_url) if it exists.
        // In PostgresSQL, Laravel's $table->unique() creates a constraint, not a bare index.
        DB::statement('ALTER TABLE webpages DROP CONSTRAINT IF EXISTS webpages_canonical_url_unique');

        // As a safety net, try dropping an index with the same name if it exists (should be removed with the constraint already).
        DB::statement('DROP INDEX IF EXISTS webpages_canonical_url_unique');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the unique constraint if rolling back, only if the column exists and constraint is missing
        if (Schema::hasColumn('webpages', 'canonical_url')) {
            $exists = DB::selectOne(
                <<<'SQL'
                SELECT 1 AS exists
                FROM pg_constraint c
                JOIN pg_class t ON t.oid = c.conrelid
                JOIN pg_namespace n ON n.oid = t.relnamespace
                WHERE n.nspname = 'public'
                  AND t.relname = 'webpages'
                  AND c.conname = 'webpages_canonical_url_unique'
                LIMIT 1
                SQL
            );

            if (!$exists) {
                DB::statement('ALTER TABLE webpages ADD CONSTRAINT webpages_canonical_url_unique UNIQUE (canonical_url)');
            }
        }
    }
};
