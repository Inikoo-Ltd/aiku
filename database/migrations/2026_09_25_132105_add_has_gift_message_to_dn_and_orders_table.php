<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('has_gift_message')->nullable();
            $table->text('gift_message')->nullable();
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->boolean('has_gift_message')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('has_gift_message');
            $table->dropColumn('gift_message');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('has_gift_message');
        });
    }
};
