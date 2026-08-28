<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 19:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('purchase_order_transactions', function (Blueprint $table) {
            $table->unsignedInteger('agent_supplier_purchase_order_id')->nullable()->index();
            $table->foreign('agent_supplier_purchase_order_id')->references('id')->on('agent_supplier_purchase_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_transactions', function (Blueprint $table) {
            $table->dropForeign(['agent_supplier_purchase_order_id']);
            $table->dropColumn('agent_supplier_purchase_order_id');
        });
    }
};
