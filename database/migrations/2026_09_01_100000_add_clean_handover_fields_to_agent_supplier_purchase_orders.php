<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Sept 2026 10:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('agent_supplier_purchase_orders', function (Blueprint $table) {
            $table->dateTimeTz('proposed_ready_at')->nullable();
            $table->dateTimeTz('approved_ready_at')->nullable()->index();
            $table->dateTimeTz('handed_over_at')->nullable();
            $table->dateTimeTz('qc_passed_at')->nullable();
            $table->dateTimeTz('compliance_complete_at')->nullable();
            $table->boolean('chs_excluded')->default(false);
            $table->text('chs_exclusion_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_supplier_purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'proposed_ready_at',
                'approved_ready_at',
                'handed_over_at',
                'qc_passed_at',
                'compliance_complete_at',
                'chs_excluded',
                'chs_exclusion_reason',
            ]);
        });
    }
};
