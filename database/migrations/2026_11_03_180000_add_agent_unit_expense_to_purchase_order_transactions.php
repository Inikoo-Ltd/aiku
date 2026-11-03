<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('purchase_order_transactions', function (Blueprint $table) {
            $table->decimal('agent_unit_expense', 18, 4)->nullable()->comment('Unit expense the agent set on the line, in the purchase order currency; replaces the supplier product Group estimate (data.unit_expense)');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_transactions', function (Blueprint $table) {
            $table->dropColumn('agent_unit_expense');
        });
    }
};
