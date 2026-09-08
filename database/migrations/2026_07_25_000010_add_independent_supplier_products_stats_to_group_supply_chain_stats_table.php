<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('group_supply_chain_stats', 'number_independent_supplier_products')) {
            return;
        }
        Schema::table('group_supply_chain_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_independent_supplier_products')->default(0)->comment('supplier products with no agent');
            $table->unsignedInteger('number_supplier_products_in_agents')->default(0)->comment('supplier products belonging to agent suppliers');
        });
    }

    public function down(): void
    {
        Schema::table('group_supply_chain_stats', function (Blueprint $table) {
            $table->dropColumn(['number_independent_supplier_products', 'number_supplier_products_in_agents']);
        });
    }
};
