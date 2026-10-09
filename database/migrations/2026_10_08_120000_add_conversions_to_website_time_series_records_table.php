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
        Schema::table('website_time_series_records', function (Blueprint $table) {
            $table->unsignedInteger('add_to_baskets')->default(0);
            $table->unsignedInteger('checkouts')->default(0);
            $table->unsignedInteger('purchases')->default(0);
            $table->decimal('revenue', 16)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('website_time_series_records', function (Blueprint $table) {
            $table->dropColumn(['add_to_baskets', 'checkouts', 'purchases', 'revenue']);
        });
    }
};
