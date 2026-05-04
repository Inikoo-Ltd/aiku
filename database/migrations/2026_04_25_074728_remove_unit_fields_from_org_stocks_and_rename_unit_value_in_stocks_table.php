<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Apr 2026 13:33:23 Nepal Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            if (Schema::hasColumn('org_stocks', 'unit_value')) {
                $table->dropColumn('unit_value');
            }

            if (Schema::hasColumn('org_stocks', 'unit_cost')) {
                $table->dropColumn('unit_cost');
            }
        });

        Schema::table('stocks', function (Blueprint $table) {
            if (Schema::hasColumn('stocks', 'unit_value') && !Schema::hasColumn('stocks', 'value_in_warehouses')) {
                $table->renameColumn('unit_value', 'value_in_warehouses');
            }
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('org_stocks', 'unit_value')) {
                $table->decimal('unit_value', 16, 3)->default(0);
            }

            if (!Schema::hasColumn('org_stocks', 'unit_cost')) {
                $table->decimal('unit_cost', 16, 3)->default(0);
            }
        });

        Schema::table('stocks', function (Blueprint $table) {
            if (Schema::hasColumn('stocks', 'value_in_warehouses') && !Schema::hasColumn('stocks', 'unit_value')) {
                $table->renameColumn('value_in_warehouses', 'unit_value');
            }
        });
    }
};
