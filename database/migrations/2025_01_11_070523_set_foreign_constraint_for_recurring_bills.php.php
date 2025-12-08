<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Jan 2025 16:41:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_bill_stats', function (Blueprint $table) {
            $table->foreign('recurring_bill_id')->references('id')->on('recurring_bills')->onDelete('cascade');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('recurring_bill_id')->references('id')->on('recurring_bills')->nullOnDelete();
        });

        Schema::table('recurring_bill_transactions', function (Blueprint $table) {
            $table->foreign('recurring_bill_id')->references('id')->on('recurring_bills')->cascadeOnDelete();
        });

        Schema::table('model_has_recurring_bills', function (Blueprint $table) {
            $table->foreign('recurring_bill_id')->references('id')->on('recurring_bills')->cascadeOnDelete();
        });

        Schema::table('fulfilment_customers', function (Blueprint $table) {
            $table->foreign('current_recurring_bill_id')->references('id')->on('recurring_bills')->nullOnDelete();
        });

        Schema::table('pallets', function (Blueprint $table) {
            $table->foreign('current_recurring_bill_id')->references('id')->on('recurring_bills')->nullOnDelete();
        });

        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->foreign('recurring_bill_transaction_id')->references('id')->on('recurring_bill_transactions')->nullOnDelete();
        });

    }

    public function down(): void
    {
        //
    }
};
