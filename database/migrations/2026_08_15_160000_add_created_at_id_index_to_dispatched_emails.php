<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Aug 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * CONCURRENTLY cannot run inside a transaction.
     */
    public $withinTransaction = false;

    /**
     * The retention archiver walks dispatched emails oldest first. Without this index nothing covers
     * created_at, so the planner scans the primary key and reads every row from disk to test its
     * date; measured at 24 seconds per batch on production, and it degrades as the remaining rows
     * thin out. Matches the archiver's (created_at, id) ordering so the scan is index-only.
     */
    private string $name = 'dispatched_emails_created_at_id_index';

    public function up(): void
    {
        $isPostgres   = DB::getDriverName() === 'pgsql';
        $concurrently = $isPostgres ? 'CONCURRENTLY ' : '';

        if ($isPostgres) {
            $invalid = DB::selectOne(
                'select 1 as found from pg_index i join pg_class c on c.oid = i.indexrelid where c.relname = ? and not i.indisvalid',
                [$this->name]
            );

            if ($invalid) {
                DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$this->name}");
            }
        }

        DB::statement("CREATE INDEX {$concurrently}IF NOT EXISTS {$this->name} ON dispatched_emails (created_at, id)");
    }

    public function down(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '';
        DB::statement("DROP INDEX {$concurrently}IF EXISTS {$this->name}");
    }
};
