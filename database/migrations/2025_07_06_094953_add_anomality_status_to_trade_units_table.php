<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 11:03:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->string('anomality_status')->index()->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->dropColumn('anomality_status');
        });
    }
};
