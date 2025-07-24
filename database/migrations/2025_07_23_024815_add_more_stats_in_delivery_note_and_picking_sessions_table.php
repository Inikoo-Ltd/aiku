<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 11:55:01 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->decimal('quantity_picked', 18, 6)->default(0)->nullable();
            $table->decimal('quantity_packed', 18, 6)->default(0)->nullable();
        });

        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->decimal('quantity_picked', 18, 6)->default(0)->nullable();
            $table->decimal('quantity_packed', 18, 6)->default(0)->nullable();
            $table->decimal('picking_percentage', 5)->default(0);
            $table->decimal('packing_percentage', 5)->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('quantity_picked');
            $table->dropColumn('quantity_packed');
        });

        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->dropColumn('quantity_picked');
            $table->dropColumn('quantity_packed');
        });
    }
};
