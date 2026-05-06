<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Apr 2026 12:49:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('org_stocks', 'sku_cost')) {
                $table->decimal('sku_value', 16, )->nullable();
            }

            if (!Schema::hasColumn('org_stocks', 'next_delivery_sku_cost')) {
                $table->decimal('current_supplier_sku_cost', 16)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            if (Schema::hasColumn('org_stocks', 'sku_value')) {
                $table->dropColumn('sku_value');
            }

            if (Schema::hasColumn('org_stocks', 'current_supplier_sku_cost')) {
                $table->dropColumn('current_supplier_sku_cost');
            }
        });
    }
};
