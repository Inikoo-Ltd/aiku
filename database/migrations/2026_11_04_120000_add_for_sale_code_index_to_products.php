<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS products_for_sale_shop_id_code_index ON products (shop_id, code) INCLUDE (id) WHERE is_for_sale AND status = 'for-sale' AND state IN ('active', 'discontinuing') AND deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_for_sale_shop_id_code_index');
    }
};
