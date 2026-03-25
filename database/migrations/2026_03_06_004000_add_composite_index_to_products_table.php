<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 06 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['shop_id', 'is_main', 'code'], 'products_shop_id_is_main_code_index');
            $table->index(['shop_id', 'is_main', 'state', 'code'], 'products_shop_id_is_main_state_code_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_shop_id_is_main_code_index');
            $table->dropIndex('products_shop_id_is_main_state_code_index');
        });
    }
};
