<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_sales_channels', 'number_fulfilment_orders')) {
                $table->unsignedInteger('number_fulfilment_orders')->default(0);
            }
            if (!Schema::hasColumn('customer_sales_channels', 'number_fulfilment_orders_state_in_process')) {
                $table->unsignedInteger('number_fulfilment_orders_state_in_process')->default(0);
            }
            if (!Schema::hasColumn('customer_sales_channels', 'number_fulfilment_orders_state_submitted')) {
                $table->unsignedInteger('number_fulfilment_orders_state_submitted')->default(0);
            }
            if (!Schema::hasColumn('customer_sales_channels', 'number_fulfilment_orders_state_confirmed')) {
                $table->unsignedInteger('number_fulfilment_orders_state_confirmed')->default(0);
            }
            if (!Schema::hasColumn('customer_sales_channels', 'number_fulfilment_orders_state_picking')) {
                $table->unsignedInteger('number_fulfilment_orders_state_picking')->default(0);
            }
            if (!Schema::hasColumn('customer_sales_channels', 'number_fulfilment_orders_state_picked')) {
                $table->unsignedInteger('number_fulfilment_orders_state_picked')->default(0);
            }
            if (!Schema::hasColumn('customer_sales_channels', 'number_fulfilment_orders_state_dispatched')) {
                $table->unsignedInteger('number_fulfilment_orders_state_dispatched')->default(0);
            }
            if (!Schema::hasColumn('customer_sales_channels', 'number_fulfilment_orders_state_cancel')) {
                $table->unsignedInteger('number_fulfilment_orders_state_cancel')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $columns = [
                'number_fulfilment_orders',
                'number_fulfilment_orders_state_in_process',
                'number_fulfilment_orders_state_submitted',
                'number_fulfilment_orders_state_confirmed',
                'number_fulfilment_orders_state_picking',
                'number_fulfilment_orders_state_picked',
                'number_fulfilment_orders_state_dispatched',
                'number_fulfilment_orders_state_cancel',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('customer_sales_channels', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
