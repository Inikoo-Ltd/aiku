<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
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
     * The outbox daily stats query filters outbox_id with a created_at range; the existing
     * (outbox_id, state) index cannot serve the range, so it sequentially scanned the whole table.
     */
    protected string $name = 'dispatched_emails_outbox_id_created_at_index';

    public function up(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '';
        DB::statement("CREATE INDEX {$concurrently}IF NOT EXISTS {$this->name} ON dispatched_emails (outbox_id, created_at)");
    }

    public function down(): void
    {
        $concurrently = DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '';
        DB::statement("DROP INDEX {$concurrently}IF EXISTS {$this->name}");
    }
};
