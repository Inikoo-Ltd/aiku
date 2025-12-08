<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Jan 2025 16:01:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_deliveries', function (Blueprint $table) {
            $table->unsignedInteger('invoice_id')->nullable()->index();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();

            $table->unsignedInteger('recurring_bill_id')->index()->nullable();
            $table->foreign('recurring_bill_id')->references('id')->on('recurring_bills')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pallet_deliveries', function (Blueprint $table) {
            $table->dropColumn('invoice_id');
            $table->dropColumn('recurring_bill_id');
        });
    }
};
