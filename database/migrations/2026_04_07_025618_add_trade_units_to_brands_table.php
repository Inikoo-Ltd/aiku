<?php

/*
 * Author: stewicca
 * Created: 2026-04-07
 * Copyright (c) 2026, Inikoo LTD
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_trade_units')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('number_trade_units');
        });
    }
};
