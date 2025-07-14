<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Jul 2025 18:59:03 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_images')->default(0);
            $table->unsignedSmallInteger('number_public_images')->default(0);
            $table->float('images_size')->default(0);
            $table->float('public_images_size')->default(0);
        });

        Schema::table('collection_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_images')->default(0);
            $table->unsignedSmallInteger('number_public_images')->default(0);
            $table->float('images_size')->default(0);
            $table->float('public_images_size')->default(0);
        });

        Schema::table('product_category_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_images')->default(0);
            $table->unsignedSmallInteger('number_public_images')->default(0);
            $table->float('images_size')->default(0);
            $table->float('public_images_size')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('product_stats', function (Blueprint $table) {
            $table->dropColumn(['number_images', 'number_public_images', 'images_size', 'public_images_size']);
        });

        Schema::table('collection_stats', function (Blueprint $table) {
            $table->dropColumn(['number_images', 'number_public_images', 'images_size', 'public_images_size']);
        });

        Schema::table('product_category_stats', function (Blueprint $table) {
            $table->dropColumn(['number_images', 'number_public_images', 'images_size', 'public_images_size']);
        });
    }
};
