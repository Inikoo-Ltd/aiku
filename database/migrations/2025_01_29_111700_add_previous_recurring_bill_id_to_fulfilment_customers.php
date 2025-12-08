<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jan 2025 19:17:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfilment_customers', function (Blueprint $table) {
            $table->unsignedInteger('previous_recurring_bill_id')->nullable()->index()->comment('Safeguard in case consolidation of current bill fails');
            $table->foreign('previous_recurring_bill_id')->references('id')->on('recurring_bills')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fulfilment_customers', function (Blueprint $table) {
            $table->dropForeign('fulfilment_customers_previous_recurring_bill_id_foreign');
            $table->dropColumn('previous_recurring_bill_id');
        });
    }
};
