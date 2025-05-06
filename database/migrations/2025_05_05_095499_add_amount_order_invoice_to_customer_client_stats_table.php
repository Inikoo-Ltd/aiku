<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 May 2025 14:18:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_client_stats', function (Blueprint $table) {
            $table->decimal('orders_amount', 16)->default(0);
            $table->decimal('orders_amount_state_dispatched', 16)->default(0);
            $table->decimal('invoices_amount', 16)->default(0);
            $table->unsignedInteger('number_current_orders')->default(0)->comment('Number of orders has state submitted, in_warehouse, handling, handling_blocked, packed, finalised');
            $table->decimal('current_orders_amount', 16)->default(0)->comment('Total amount of orders has state submitted, in_warehouse, handling, handling_blocked, packed, finalised');
        });
    }


    public function down(): void
    {
        Schema::table('customer_client_stats', function (Blueprint $table) {
            $table->dropColumn('orders_amount');
            $table->dropColumn('orders_amount_state_dispatched');
            $table->dropColumn('invoices_amount');
            $table->dropColumn('number_current_orders');
            $table->dropColumn('current_orders_amount');
        });
    }
};
