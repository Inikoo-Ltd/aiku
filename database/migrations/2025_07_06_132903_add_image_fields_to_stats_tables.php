<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 14:39:29 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Add average_image_size to tables that already have number_images and images_size (total_image_size)
        Schema::table('product_stats', function (Blueprint $table) {
            $table->float('average_image_size')->default(0);
            $table->float('max_image_size')->nullable();
        });

        Schema::table('product_category_stats', function (Blueprint $table) {
            $table->float('average_image_size')->default(0);
            $table->float('max_image_size')->nullable();
        });

        Schema::table('collection_stats', function (Blueprint $table) {
            $table->float('average_image_size')->default(0);
            $table->float('max_image_size')->nullable();
        });

        // Add all three fields to tables that don't have image fields yet
        Schema::table('trade_unit_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_images')->default(0);
            $table->float('total_image_size')->default(0);
            $table->float('average_image_size')->default(0);
            $table->float('max_image_size')->nullable();
        });

        Schema::table('stock_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_images')->default(0);
            $table->float('total_image_size')->default(0);
            $table->float('average_image_size')->default(0);
            $table->float('max_image_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_stats', function (Blueprint $table) {
            $table->dropColumn(['average_image_size', 'max_image_size']);
        });

        Schema::table('product_category_stats', function (Blueprint $table) {
            $table->dropColumn(['average_image_size', 'max_image_size']);
        });

        Schema::table('collection_stats', function (Blueprint $table) {
            $table->dropColumn(['average_image_size', 'max_image_size']);
        });

        Schema::table('trade_unit_stats', function (Blueprint $table) {
            $table->dropColumn(['number_images', 'total_image_size', 'average_image_size', 'max_image_size']);
        });

        Schema::table('stock_stats', function (Blueprint $table) {
            $table->dropColumn(['number_images', 'total_image_size', 'average_image_size', 'max_image_size']);
        });
    }
};
