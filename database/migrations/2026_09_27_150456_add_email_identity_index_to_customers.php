<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * CONCURRENTLY cannot run inside a transaction.
     */
    public $withinTransaction = false;

    private string $name = 'customers_group_id_identity_email_index';

    /**
     * Sister-shop matching joins customers across a group on their normalised email; without this
     * index every shop's hydration would scan the whole customers table once per buyer.
     */
    public function up(): void
    {
        $isPostgres   = DB::getDriverName() === 'pgsql';
        $concurrently = $isPostgres ? 'CONCURRENTLY ' : '';

        if ($isPostgres && DB::selectOne('select 1 as found from pg_index i join pg_class c on c.oid = i.indexrelid where c.relname = ? and not i.indisvalid', [$this->name])) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$this->name}");
        }

        DB::statement("CREATE INDEX {$concurrently}IF NOT EXISTS {$this->name} ON customers (group_id, lower(trim(email))) WHERE email IS NOT NULL AND deleted_at IS NULL");
    }

    public function down(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '';
        DB::statement("DROP INDEX {$concurrently}IF EXISTS {$this->name}");
    }
};
