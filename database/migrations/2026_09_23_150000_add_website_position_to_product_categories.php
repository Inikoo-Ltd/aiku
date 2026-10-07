<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('website_position')->nullable()->index()->comment('hand picked order of the families in the website family blocks, null means not curated');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('website_position')->nullable()->index()->comment('hand picked order of the families in the website family blocks, followed from the master, null means not curated');
        });
    }

    public function down(): void
    {
        Schema::table('master_product_categories', function (Blueprint $table) {
            $table->dropColumn('website_position');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('website_position');
        });
    }
};
