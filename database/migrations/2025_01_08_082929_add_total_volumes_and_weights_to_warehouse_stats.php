<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jan 2025 03:59:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('location_stats', function (Blueprint $table) {
            $table->decimal('total_volume', 18)->default(0)->comment('cbm');
            $table->decimal('total_weight', 18)->default(0)->comment('kg');
        });
        Schema::table('warehouse_area_stats', function (Blueprint $table) {
            $table->decimal('total_volume', 18)->default(0)->comment('cbm');
            $table->decimal('total_weight', 18)->default(0)->comment('kg');
        });
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->decimal('total_volume', 18)->default(0)->comment('cbm');
            $table->decimal('total_weight', 18)->default(0)->comment('kg');
        });
    }

    public function down(): void
    {
        Schema::table('location_stats', function (Blueprint $table) {
            $table->dropColumn(['total_volume', 'total_weight']);
        });

        Schema::table('warehouse_area_stats', function (Blueprint $table) {
            $table->dropColumn(['total_volume', 'total_weight']);
        });

        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->dropColumn(['total_volume', 'total_weight']);
        });
    }
};
