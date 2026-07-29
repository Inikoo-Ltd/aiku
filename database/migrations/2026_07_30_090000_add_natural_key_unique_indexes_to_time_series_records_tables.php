<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jul 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * 28 dedupes + partitioned unique index builds take too many locks for one
     * transaction on default max_locks_per_transaction. Every statement is
     * idempotent (IF NOT EXISTS, re-runnable dedupe), so no transaction needed.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        foreach ($this->tables() as $table) {
            $keyColumns = $this->keyColumns($table);
            $joinOn     = implode(' AND ', array_map(fn ($c) => "a.$c = b.$c", $keyColumns));

            DB::statement("DELETE FROM \"$table\" a USING \"$table\" b WHERE $joinOn AND a.id < b.id");
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS {$table}_nk_unique ON \"$table\" (".implode(', ', $keyColumns).')');
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $table) {
            DB::statement("DROP INDEX IF EXISTS {$table}_nk_unique");
        }
    }

    /** @return array<int, string> */
    private function tables(): array
    {
        return array_map(
            fn ($row) => $row->tablename,
            DB::select("select tablename from pg_tables where schemaname='public' and tablename like '%\_time\_series\_records' order by tablename")
        );
    }

    /** @return array<int, string> */
    private function keyColumns(string $table): array
    {
        $seriesColumn = substr($table, 0, -8).'_id';

        $columns = $table === 'platform_time_series_records'
            ? [$seriesColumn, 'shop_id', 'period', 'frequency']
            : [$seriesColumn, 'period', 'frequency'];

        $partitionColumns = array_map(
            fn ($row) => $row->attname,
            DB::select("
                select a.attname
                from pg_partitioned_table p
                join pg_class c on c.oid = p.partrelid
                join pg_attribute a on a.attrelid = c.oid and a.attnum = any(p.partattrs)
                where c.relname = ?
            ", [$table])
        );

        return array_values(array_unique([...$columns, ...$partitionColumns]));
    }
};
