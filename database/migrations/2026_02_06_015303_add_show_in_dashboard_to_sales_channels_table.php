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
        if (!Schema::hasColumn('sales_channels', 'show_in_dashboard')) {
            Schema::table('sales_channels', function (Blueprint $table) {
                $table->boolean('show_in_dashboard')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_channels', 'show_in_dashboard')) {
            Schema::table('sales_channels', function (Blueprint $table) {
                $table->dropColumn('show_in_dashboard');
            });
        }
    }
};
