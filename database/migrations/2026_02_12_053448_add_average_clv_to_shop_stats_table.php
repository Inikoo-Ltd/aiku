<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->decimal('average_clv', 16)->default(0)->after('shop_id');
            $table->decimal('average_historic_clv', 16)->default(0)->after('average_clv');
        });
    }

    public function down(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->dropColumn(['average_clv', 'average_historic_clv']);
        });
    }
};
