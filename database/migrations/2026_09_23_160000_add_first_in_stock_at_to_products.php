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
        Schema::table('products', function (Blueprint $table) {
            $table->dateTimeTz('first_in_stock_at')->nullable()->comment('When a coming soon product first had stock to sell');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('first_in_stock_at');
        });
    }
};
