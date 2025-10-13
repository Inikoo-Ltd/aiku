<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Oct 2025 19:06:44 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    private array $tables = [
        'offer_allowances_stats',
        'transaction_has_offer_allowances',
        'invoice_transaction_has_offer_allowances',
        'invoice_has_no_invoice_transaction_offer_allowances',
        'order_has_no_transaction_offer_allowances',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if ($this->columnExists($table, 'offer_component_id') && !$this->columnExists($table, 'offer_allowance_id')) {
                // Drop any foreign keys on offer_component_id
                foreach ($this->getForeignKeysForColumn($table, 'offer_component_id') as $fk) {
                    DB::statement("ALTER TABLE \"$table\" DROP CONSTRAINT IF EXISTS \"$fk\"");
                }

                // Drop any index that focuses on offer_component_id (by common naming)
                $oldIndexName = $table.'_offer_component_id_index';
                DB::statement("DROP INDEX IF EXISTS \"$oldIndexName\"");

                // Rename the column
                DB::statement("ALTER TABLE \"$table\" RENAME COLUMN \"offer_component_id\" TO \"offer_allowance_id\"");

                // Recreate FK to offer_allowances(id) if the referenced table exists
                if ($this->tableExists('offer_allowances')) {
                    $fkName = $table.'_offer_allowance_id_foreign';
                    DB::statement("ALTER TABLE \"$table\" ADD CONSTRAINT \"$fkName\" FOREIGN KEY (offer_allowance_id) REFERENCES offer_allowances(id)");
                }

                // Ensure the index exists on the new column
                $newIndexName = $table.'_offer_allowance_id_index';
                DB::statement("CREATE INDEX IF NOT EXISTS \"$newIndexName\" ON \"$table\" (offer_allowance_id)");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if ($this->columnExists($table, 'offer_allowance_id') && !$this->columnExists($table, 'offer_component_id')) {
                // Drop any foreign keys on offer_allowance_id
                foreach ($this->getForeignKeysForColumn($table, 'offer_allowance_id') as $fk) {
                    DB::statement("ALTER TABLE \"$table\" DROP CONSTRAINT IF EXISTS \"$fk\"");
                }

                // Drop index on offer_allowance_id
                $newIndexName = $table.'_offer_allowance_id_index';
                DB::statement("DROP INDEX IF EXISTS \"$newIndexName\"");

                // Rename the column back
                DB::statement("ALTER TABLE \"$table\" RENAME COLUMN \"offer_allowance_id\" TO \"offer_component_id\"");

                // Recreate FK to offer_components(id) if present
                if ($this->tableExists('offer_components')) {
                    $fkName = $table.'_offer_component_id_foreign';
                    DB::statement("ALTER TABLE \"$table\" ADD CONSTRAINT \"$fkName\" FOREIGN KEY (offer_component_id) REFERENCES offer_components(id)");
                }

                // Recreate index with the original naming to be safe for rollbacks
                $oldIndexName = $table.'_offer_component_id_index';
                DB::statement("CREATE INDEX IF NOT EXISTS \"$oldIndexName\" ON \"$table\" (offer_component_id)");
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT to_regclass('public.' || ?) IS NOT NULL AS exists
            SQL,
            [$table]
        );

        // In Postgres, DB::selectOne returns stdClass; cast truthy
        return $row && (property_exists($row, 'exists') && $row->exists);
    }

    private function columnExists(string $table, string $column): bool
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT 1 AS exists
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
            SQL,
            [$table, $column]
        );

        return (bool)$row;
    }

    private function getForeignKeysForColumn(string $table, string $column): array
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT con.conname AS constraint_name
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_namespace nsp ON nsp.oid = con.connamespace
            JOIN pg_attribute att ON att.attrelid = rel.oid AND att.attnum = ANY (con.conkey)
            WHERE con.contype = 'f'
              AND nsp.nspname = 'public'
              AND rel.relname = ?
              AND att.attname = ?
            SQL,
            [$table, $column]
        );

        return array_map(fn ($r) => $r->constraint_name, $rows);
    }
};
