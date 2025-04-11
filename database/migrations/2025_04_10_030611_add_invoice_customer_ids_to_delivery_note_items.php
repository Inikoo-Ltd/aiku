<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Apr 2025 11:07:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {

            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedInteger('order_id')->nullable()->nullable();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->unsignedInteger('invoice_id')->nullable()->nullable();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }


    public function down(): void
    {
        Schema::table('delivery_note_items', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
            $table->dropForeign(['invoice_id']);
            $table->dropColumn('invoice_id');

        });
    }
};
