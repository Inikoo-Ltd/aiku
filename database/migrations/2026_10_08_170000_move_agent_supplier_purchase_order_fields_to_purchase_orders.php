<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 17:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dateTimeTz('proposed_ready_at')->nullable();
            $table->dateTimeTz('approved_ready_at')->nullable()->index();
            $table->dateTimeTz('compliance_complete_at')->nullable();
            $table->boolean('chs_excluded')->default(false);
            $table->text('chs_exclusion_reason')->nullable();
            $table->unsignedInteger('agent_supplier_purchase_order_id')->nullable()->index();
            $table->foreign('agent_supplier_purchase_order_id')->references('id')->on('agent_supplier_purchase_orders')->nullOnDelete();
        });

        Schema::table('aspo_deposits', function (Blueprint $table) {
            $table->unsignedInteger('agent_supplier_purchase_order_id')->nullable()->change();
            $table->unsignedInteger('purchase_order_id')->nullable()->index();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders');
        });
    }

    public function down(): void
    {
        Schema::table('aspo_deposits', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropColumn('purchase_order_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['agent_supplier_purchase_order_id']);
            $table->dropColumn([
                'proposed_ready_at',
                'approved_ready_at',
                'compliance_complete_at',
                'chs_excluded',
                'chs_exclusion_reason',
                'agent_supplier_purchase_order_id',
            ]);
        });
    }
};
