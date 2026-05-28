<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 22 May 2026 23:06:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $tables = [
            'stock_stats',
            'org_stock_stats',
            'supplier_stats',
            'agent_stats',
            'supplier_product_stats',
            'historic_supplier_product_stats',
            'purchase_orders',
            'org_agent_stats',
            'org_supplier_stats',
            'org_supplier_product_stats',
            'org_partner_stats',
            'agent_supplier_purchase_orders',
            'group_procurement_stats',
            'organisation_procurement_stats',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'number_stock_deliveries_state_booking_in')) {
                    $table->unsignedInteger('number_stock_deliveries_state_booking_in')->default(0);
                }
                if (!Schema::hasColumn($tableName, 'number_stock_deliveries_state_booked_in')) {
                    $table->unsignedInteger('number_stock_deliveries_state_booked_in')->default(0);
                }
            });
        }
    }


    public function down(): void
    {
        $tables = [
            'stock_stats',
            'org_stock_stats',
            'supplier_stats',
            'agent_stats',
            'supplier_product_stats',
            'historic_supplier_product_stats',
            'purchase_orders',
            'org_agent_stats',
            'org_supplier_stats',
            'org_supplier_product_stats',
            'org_partner_stats',
            'agent_supplier_purchase_orders',
            'group_procurement_stats',
            'organisation_procurement_stats',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'number_stock_deliveries_state_booking_in')) {
                    $table->dropColumn('number_stock_deliveries_state_booking_in');
                }
                if (Schema::hasColumn($tableName, 'number_stock_deliveries_state_booked_in')) {
                    $table->dropColumn('number_stock_deliveries_state_booked_in');
                }
            });
        }
    }
};
