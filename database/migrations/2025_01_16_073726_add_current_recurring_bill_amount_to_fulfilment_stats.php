<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Jan 2025 15:38:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfilment_stats', function (Blueprint $table) {
            $table->decimal('current_recurring_bills_amount')->default(0);
            $table->decimal('current_recurring_bills_amount_org_currency')->default(0);
            $table->decimal('current_recurring_bills_amount_grp_currency')->default(0);

        });
    }

    public function down(): void
    {
        Schema::table('fulfilment_stats', function (Blueprint $table) {
            $table->dropColumn('current_recurring_bills_amount');
            $table->dropColumn('current_recurring_bills_amount_org_currency');
            $table->dropColumn('current_recurring_bills_amount_grp_currency');

        });
    }
};
