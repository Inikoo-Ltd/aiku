<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 26 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->decimal('picking_seconds_per_sko', 8, 2)->nullable();
            $table->decimal('packing_seconds_per_sko', 8, 2)->nullable();
            $table->unsignedInteger('picking_packing_speed_sample_size')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->dropColumn([
                'picking_seconds_per_sko',
                'packing_seconds_per_sko',
                'picking_packing_speed_sample_size',
            ]);
        });
    }
};
