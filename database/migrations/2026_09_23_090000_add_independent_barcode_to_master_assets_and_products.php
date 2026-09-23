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
        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('independent_barcode')->default(false)->comment('barcode chosen by hand, no hydrator touches it');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('independent_barcode')->default(false)->comment('barcode chosen by hand, no hydrator touches it');
        });
    }

    public function down(): void
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn('independent_barcode');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('independent_barcode');
        });
    }
};
