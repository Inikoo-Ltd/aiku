<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 12 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('invoice_transaction_has_stocks', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('invoice_transaction_id');
            $table->foreign('invoice_transaction_id')->references('id')->on('invoice_transactions')->cascadeOnDelete();

            $table->unsignedInteger('stock_id');
            $table->foreign('stock_id')->references('id')->on('stocks')->cascadeOnDelete();

            $table->unsignedInteger('stock_family_id')->nullable()->index();
            $table->foreign('stock_family_id')->references('id')->on('stock_families')->nullOnDelete();

            $table->decimal('net_amount', 16)->default(0);
            $table->decimal('org_net_amount', 16)->default(0);
            $table->decimal('grp_net_amount', 16)->default(0);

            $table->unique(['invoice_transaction_id', 'stock_id'], 'invoice_transaction_stock_unique');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_transaction_has_stocks');
    }
};
