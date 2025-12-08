<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Feb 2025 12:20:13 Central Indonesia Time, Kuala Lumpur, Malaysia
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
            $table->decimal('current_recurring_bills_amount', 16)->default(0)->change();
            $table->decimal('current_recurring_bills_amount_org_currency', 16)->default(0)->change();
            $table->decimal('current_recurring_bills_amount_grp_currency', 16)->default(0)->change();

        });
    }

    public function down(): void
    {
        Schema::table('fulfilment_stats', function (Blueprint $table) {
            //
        });
    }
};
