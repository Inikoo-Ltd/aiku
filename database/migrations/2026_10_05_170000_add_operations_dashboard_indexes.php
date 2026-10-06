<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026 17:00:00 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS delivery_notes_warehouse_id_dispatched_at_live_index ON delivery_notes (warehouse_id, dispatched_at) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS orders_organisation_id_submitted_at_live_index ON orders (organisation_id, submitted_at) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS pickings_organisation_id_created_at_index ON pickings (organisation_id, created_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS pickings_organisation_id_created_at_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS orders_organisation_id_submitted_at_live_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS delivery_notes_warehouse_id_dispatched_at_live_index');
    }
};
