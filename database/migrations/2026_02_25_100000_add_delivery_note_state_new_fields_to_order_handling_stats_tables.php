<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 13:38:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $tables = [
            'shop_order_handling_stats',
            'organisation_order_handling_stats',
            'group_order_handling_stats',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                // Counts
                $table->unsignedInteger('number_delivery_notes_state_picked')->default(0);
                $table->unsignedInteger('number_delivery_notes_state_packing')->default(0);

                $table->unsignedInteger('number_orders_state_picked')->default(0);
                $table->unsignedInteger('number_orders_state_packing')->default(0);

                $table->unsignedInteger('orders_state_picked_amount')->default(0);
                $table->unsignedInteger('orders_state_picked_amount_org_currency')->default(0);
                $table->unsignedInteger('orders_state_picked_amount_grp_currency')->default(0);

                $table->unsignedInteger('orders_state_packing_amount')->default(0);
                $table->unsignedInteger('orders_state_packing_amount_org_currency')->default(0);
                $table->unsignedInteger('orders_state_packing_amount_grp_currency')->default(0);


                // Weights
                $table->decimal('weight_delivery_notes_state_picked', 16)->default(0);
                $table->decimal('weight_delivery_notes_state_packing', 16)->default(0);

                // Items
                $table->unsignedInteger('number_items_delivery_notes_state_picked')->default(0);
                $table->unsignedInteger('number_items_delivery_notes_state_packing')->default(0);
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'shop_order_handling_stats',
            'organisation_order_handling_stats',
            'group_order_handling_stats',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'number_delivery_notes_state_picked',
                    'number_delivery_notes_state_packing',
                    'weight_delivery_notes_state_picked',
                    'weight_delivery_notes_state_packing',
                    'number_items_delivery_notes_state_picked',
                    'number_items_delivery_notes_state_packing',
                    'number_orders_state_picked',
                    'number_orders_state_packing',
                ]);
            });
        }
    }
};
