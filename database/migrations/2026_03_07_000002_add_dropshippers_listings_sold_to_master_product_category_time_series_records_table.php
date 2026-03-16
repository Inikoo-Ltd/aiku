<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 07 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('master_product_category_time_series_records') && !Schema::hasColumn('master_product_category_time_series_records', 'dropshippers')) {
            Schema::table('master_product_category_time_series_records', function (Blueprint $table) {
                $table->unsignedInteger('dropshippers')->default(0);
                $table->unsignedInteger('listings')->default(0);
                $table->unsignedInteger('sold')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('master_product_category_time_series_records')) {
            Schema::table('master_product_category_time_series_records', function (Blueprint $table) {
                if (Schema::hasColumn('master_product_category_time_series_records', 'dropshippers')) {
                    $table->dropColumn('dropshippers');
                }
                if (Schema::hasColumn('master_product_category_time_series_records', 'listings')) {
                    $table->dropColumn('listings');
                }
                if (Schema::hasColumn('master_product_category_time_series_records', 'sold')) {
                    $table->dropColumn('sold');
                }
            });
        }
    }
};
