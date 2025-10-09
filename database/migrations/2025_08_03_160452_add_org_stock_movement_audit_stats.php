<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 03 Aug 2025 18:05:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('org_stock_stats', 'number_org_stock_movements_type_audit')) {
            Schema::table('org_stock_stats', function (Blueprint $table) {
                $table->unsignedBigInteger('number_org_stock_movements_type_audit')->default(0)->after('number_org_stock_movements');
            });
        }

        if (!Schema::hasColumn('warehouse_stats', 'number_org_stock_movements_type_audit')) {
            Schema::table('warehouse_stats', function (Blueprint $table) {
                $table->unsignedBigInteger('number_org_stock_movements_type_audit')->default(0)->after('number_org_stock_movements');
            });
        }

        if (!Schema::hasColumn('organisation_inventory_stats', 'number_org_stock_movements_type_audit')) {
            Schema::table('organisation_inventory_stats', function (Blueprint $table) {
                $table->unsignedBigInteger('number_org_stock_movements_type_audit')->default(0)->after('number_org_stock_movements');
            });
        }

        if (!Schema::hasColumn('group_inventory_stats', 'number_org_stock_movements_type_audit')) {
            Schema::table('group_inventory_stats', function (Blueprint $table) {
                $table->unsignedBigInteger('number_org_stock_movements_type_audit')->default(0)->after('number_org_stock_movements');
            });
        }
    }


    public function down(): void
    {
        if (Schema::hasColumn('org_stock_stats', 'number_org_stock_movements_type_audit')) {
            Schema::table('org_stock_stats', function (Blueprint $table) {
                $table->dropColumn('number_org_stock_movements_type_audit');
            });
        }

        if (Schema::hasColumn('warehouse_stats', 'number_org_stock_movements_type_audit')) {
            Schema::table('warehouse_stats', function (Blueprint $table) {
                $table->dropColumn('number_org_stock_movements_type_audit');
            });
        }

        if (Schema::hasColumn('organisation_inventory_stats', 'number_org_stock_movements_type_audit')) {
            Schema::table('organisation_inventory_stats', function (Blueprint $table) {
                $table->dropColumn('number_org_stock_movements_type_audit');
            });
        }

        if (Schema::hasColumn('group_inventory_stats', 'number_org_stock_movements_type_audit')) {
            Schema::table('group_inventory_stats', function (Blueprint $table) {
                $table->dropColumn('number_org_stock_movements_type_audit');
            });
        }
    }
};
