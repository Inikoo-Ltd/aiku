<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Apr 2025 11:02:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('amount_in_basket', 16)->default(0);
            $table->unsignedInteger('current_order_in_basket_id')->nullable()->index();
            $table->foreign('current_order_in_basket_id')->references('id')->on('orders')->nullOnDelete();
        });
        Schema::table('customer_clients', function (Blueprint $table) {
            $table->decimal('amount_in_basket', 16)->default(0);
            $table->unsignedInteger('current_order_in_basket_id')->nullable()->index();
            $table->foreign('current_order_in_basket_id')->references('id')->on('orders')->nullOnDelete();
        });
    }


    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['amount_in_basket']);
            $table->dropForeign(['current_order_in_basket_id']);
            $table->dropColumn('current_order_in_basket_id');
        });
        Schema::table('customer_clients', function (Blueprint $table) {
            $table->dropColumn(['amount_in_basket']);
            $table->dropForeign(['current_order_in_basket_id']);
            $table->dropColumn('current_order_in_basket_id');
        });
    }
};
