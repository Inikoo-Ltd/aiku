<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('manufacture_breaks', function (Blueprint $table) {
            $table->unsignedBigInteger('clock_out_clocking_id')->nullable();
            $table->foreign('clock_out_clocking_id')->references('id')->on('clockings')->nullOnDelete();
            $table->unsignedBigInteger('clock_in_clocking_id')->nullable();
            $table->foreign('clock_in_clocking_id')->references('id')->on('clockings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_breaks', function (Blueprint $table) {
            $table->dropForeign(['clock_out_clocking_id']);
            $table->dropForeign(['clock_in_clocking_id']);
            $table->dropColumn(['clock_out_clocking_id', 'clock_in_clocking_id']);
        });
    }
};
