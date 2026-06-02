<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 31 May 2026 17:43:37 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {

    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->index(['customer_sales_channel_id', 'item_type', 'item_id'], 'portfolios_search_optimization_idx');
            $table->index(['customer_sales_channel_id', 'item_type', 'item_id', 'status', 'item_code', 'reference'], 'portfolios_covering_export_idx');
        });
        DB::statement("CREATE INDEX portfolios_product_export_idx ON portfolios (customer_sales_channel_id, item_id) WHERE item_type = 'Product'");
    }


    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropIndex('portfolios_search_optimization_idx');
            $table->dropIndex('portfolios_product_export_idx');
            $table->dropIndex('portfolios_covering_export_idx');
        });
    }
};
