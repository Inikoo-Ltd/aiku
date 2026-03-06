<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 05 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('asset_time_series_records') && !Schema::hasColumn('asset_time_series_records', 'dropshippers')) {
            Schema::table('asset_time_series_records', function (Blueprint $table) {
                $table->unsignedInteger('dropshippers')->default(0);
                $table->unsignedInteger('listings')->default(0);
                $table->unsignedInteger('sold')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('asset_time_series_records')) {
            Schema::table('asset_time_series_records', function (Blueprint $table) {
                if (Schema::hasColumn('asset_time_series_records', 'dropshippers')) {
                    $table->dropColumn('dropshippers');
                }
                if (Schema::hasColumn('asset_time_series_records', 'listings')) {
                    $table->dropColumn('listings');
                }
                if (Schema::hasColumn('asset_time_series_records', 'sold')) {
                    $table->dropColumn('sold');
                }
            });
        }
    }
};
