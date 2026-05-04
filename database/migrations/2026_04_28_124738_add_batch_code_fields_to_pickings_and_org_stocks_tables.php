<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 28 Apr 2026 12:47:38 Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('pickings', function (Blueprint $table) {
            $table->unsignedInteger('batch_code_id')->nullable()->index()->after('org_stock_id');
            $table->foreign('batch_code_id')->references('id')->on('batch_codes');
        });

        Schema::table('org_stocks', function (Blueprint $table) {
            $table->unsignedInteger('current_batch_codes')->default(0)->index()->after('quantity_available');
            $table->unsignedInteger('main_batch_code_id')->nullable()->index()->after('current_batch_codes');
            $table->foreign('main_batch_code_id')->references('id')->on('batch_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropForeign(['main_batch_code_id']);
            $table->dropColumn(['current_batch_codes', 'main_batch_code_id']);
        });

        Schema::table('pickings', function (Blueprint $table) {
            $table->dropForeign(['batch_code_id']);
            $table->dropColumn('batch_code_id');
        });
    }
};
