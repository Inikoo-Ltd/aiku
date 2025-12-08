<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Nov 2025 22:38:17 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_has_org_stocks', function (Blueprint $table) {
            // Composite index on (product_id, org_stock_id)
            $table->index(['product_id', 'org_stock_id'], 'phos_product_id_org_stock_id_index');

            // Individual indexes
            $table->index('product_id', 'phos_product_id_index');
            $table->index('org_stock_id', 'phos_org_stock_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_has_org_stocks', function (Blueprint $table) {
            $table->dropIndex('phos_product_id_org_stock_id_index');
            $table->dropIndex('phos_product_id_index');
            $table->dropIndex('phos_org_stock_id_index');
        });
    }
};
