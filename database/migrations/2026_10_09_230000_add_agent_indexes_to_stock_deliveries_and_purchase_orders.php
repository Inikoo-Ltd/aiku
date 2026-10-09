<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS stock_deliveries_agent_id_state_index ON stock_deliveries (agent_id, state) WHERE agent_id IS NOT NULL');
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS purchase_orders_agent_id_state_index ON purchase_orders (agent_id, state) WHERE agent_id IS NOT NULL AND deleted_at IS NULL AND parent_type = 'OrgSupplier'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS stock_deliveries_agent_id_state_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS purchase_orders_agent_id_state_index');
    }
};
