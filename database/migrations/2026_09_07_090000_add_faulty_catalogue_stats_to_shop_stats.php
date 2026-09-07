<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 07 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_families_no_images')->default(0)->change();
            $table->unsignedInteger('number_products_no_images')->default(0)->change();

            $table->unsignedInteger('number_products_mismatch_family')->default(0);
            $table->unsignedInteger('number_products_no_description')->default(0);
            $table->unsignedInteger('number_products_not_online')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_products_mismatch_family',
                'number_products_no_description',
                'number_products_not_online',
            ]);

            $table->unsignedSmallInteger('number_families_no_images')->default(0)->change();
            $table->unsignedSmallInteger('number_products_no_images')->default(0)->change();
        });
    }
};
