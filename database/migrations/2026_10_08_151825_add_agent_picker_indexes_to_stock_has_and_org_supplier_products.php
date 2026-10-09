<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 15:18:25 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS stock_has_supplier_products_supplier_product_id_index ON stock_has_supplier_products (supplier_product_id)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS org_supplier_products_org_agent_id_index ON org_supplier_products (org_agent_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS org_supplier_products_org_agent_id_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS stock_has_supplier_products_supplier_product_id_index');
    }
};
