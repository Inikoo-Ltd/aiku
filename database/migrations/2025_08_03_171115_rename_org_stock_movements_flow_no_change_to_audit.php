<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 03 Aug 2025 19:12:26 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('org_stock_stats', 'number_org_stock_movements_flow_no_change')) {
            Schema::table('org_stock_stats', function (Blueprint $table) {
                $table->renameColumn('number_org_stock_movements_flow_no_change', 'number_org_stock_movements_flow_audit');
            });
        }

        if (Schema::hasColumn('warehouse_stats', 'number_org_stock_movements_flow_no_change')) {
            Schema::table('warehouse_stats', function (Blueprint $table) {
                $table->renameColumn('number_org_stock_movements_flow_no_change', 'number_org_stock_movements_flow_audit');
            });
        }

        if (Schema::hasColumn('organisation_inventory_stats', 'number_org_stock_movements_flow_no_change')) {
            Schema::table('organisation_inventory_stats', function (Blueprint $table) {
                $table->renameColumn('number_org_stock_movements_flow_no_change', 'number_org_stock_movements_flow_audit');
            });
        }

        if (Schema::hasColumn('group_inventory_stats', 'number_org_stock_movements_flow_no_change')) {
            Schema::table('group_inventory_stats', function (Blueprint $table) {
                $table->renameColumn('number_org_stock_movements_flow_no_change', 'number_org_stock_movements_flow_audit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('org_stock_stats', 'number_org_stock_movements_flow_audit')) {
            Schema::table('org_stock_stats', function (Blueprint $table) {
                $table->renameColumn('number_org_stock_movements_flow_audit', 'number_org_stock_movements_flow_no_change');
            });
        }

        if (Schema::hasColumn('warehouse_stats', 'number_org_stock_movements_flow_audit')) {
            Schema::table('warehouse_stats', function (Blueprint $table) {
                $table->renameColumn('number_org_stock_movements_flow_audit', 'number_org_stock_movements_flow_no_change');
            });
        }

        if (Schema::hasColumn('organisation_inventory_stats', 'number_org_stock_movements_flow_audit')) {
            Schema::table('organisation_inventory_stats', function (Blueprint $table) {
                $table->renameColumn('number_org_stock_movements_flow_audit', 'number_org_stock_movements_flow_no_change');
            });
        }

        if (Schema::hasColumn('group_inventory_stats', 'number_org_stock_movements_flow_audit')) {
            Schema::table('group_inventory_stats', function (Blueprint $table) {
                $table->renameColumn('number_org_stock_movements_flow_audit', 'number_org_stock_movements_flow_no_change');
            });
        }
    }
};
