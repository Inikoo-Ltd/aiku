<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Jul 2025 21:54:05 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_areas', function (Blueprint $table) {
            $table->float('picking_position')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('warehouse_areas', function (Blueprint $table) {
            $table->dropColumn('picking_position');
        });
    }
};
