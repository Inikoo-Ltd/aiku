<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 23:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX agent_supplier_purchase_orders_po_supplier_unique
             ON agent_supplier_purchase_orders (purchase_order_id, supplier_id)
             WHERE deleted_at IS NULL AND purchase_order_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS agent_supplier_purchase_orders_po_supplier_unique');
    }
};
