<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Jun 2025 15:48:05 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('top_seller')->nullable()->index();
        });
        Schema::table('product_categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('top_seller')->nullable()->index();
        });


    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('top_seller');
        });
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('top_seller');
        });
    }
};
