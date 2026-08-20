<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('invoice_transaction_date_repairs', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_transaction_id')->primary();
            $table->timestampTz('old_date');
            $table->timestampTz('new_date');
            $table->timestampTz('repaired_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_transaction_date_repairs');
    }
};
