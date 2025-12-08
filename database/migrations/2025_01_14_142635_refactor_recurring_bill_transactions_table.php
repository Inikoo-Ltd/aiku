<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jan 2025 22:26:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_bill_transactions', function (Blueprint $table) {
            $table->dropColumn('grp_exchange');
            $table->dropColumn('org_exchange');
            $table->dropColumn('source_id');

            $table->decimal('unit_cost', 16, 3)->default(0);
            $table->decimal('discount_percentage')->default(0);
            $table->decimal('temporal_quantity', 16, 3)->default(1);

        });
    }

    public function down(): void
    {
        Schema::table('recurring_bill_transactions', function (Blueprint $table) {
            $table->string('source_id');
            $table->decimal('grp_exchange');
            $table->decimal('org_exchange');
            $table->dropColumn('discount_percentage');
            $table->dropColumn('temporal_quantity');
            $table->dropColumn('unit_cost');
        });
    }
};
