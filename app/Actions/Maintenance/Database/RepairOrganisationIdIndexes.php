<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 Oct 2025 15:16:46 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Database;

use App\Actions\Traits\WithActionUpdate;
use Illuminate\Support\Facades\DB;

class RepairOrganisationIdIndexes
{
    use WithActionUpdate;

    public string $commandSignature = 'db:repair_organisation_id_indexes';

    public function asCommand(): void
    {
        // Discover all base tables in the public schema that have an organisation_id column
        $tables = collect(DB::select(<<<'SQL'
            SELECT c.table_name
            FROM information_schema.columns c
            JOIN information_schema.tables t
              ON t.table_schema = c.table_schema AND t.table_name = c.table_name
            WHERE c.table_schema = 'public'
              AND t.table_type = 'BASE TABLE'
              AND c.column_name = 'organisation_id'
            ORDER BY c.table_name
        SQL))->pluck('table_name');

        $created = 0;
        foreach ($tables as $table) {
            // Robustly check via system catalogs if any index already includes the organisation_id column
            $indexed = DB::selectOne(
                <<<'SQL'
                SELECT 1 AS exists
                FROM pg_class t
                JOIN pg_namespace ns ON ns.oid = t.relnamespace
                JOIN pg_index i ON i.indrelid = t.oid
                JOIN pg_class ix ON ix.oid = i.indexrelid
                JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(i.indkey)
                WHERE ns.nspname = 'public'
                  AND t.relname = ?
                  AND a.attname = 'organisation_id'
                LIMIT 1
                SQL,
                [$table]
            );

            if (!$indexed) {
                $indexName = $table . '_organisation_id_index';
                // Create the index if it does not exist (by name)
                DB::statement("CREATE INDEX IF NOT EXISTS $indexName ON $table (organisation_id)");
                $created++;
            }
        }

        // Provide some feedback in the console
        if (method_exists($this, 'info')) {
            $this->info("Organisation indexes repair complete. Created $created indexes across {$tables->count()} tables.");
        }
    }
}
