<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 11:18:57 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('trade_unit_stats', function (Blueprint $table) {
            $table->unsignedMediumInteger('number_stocks')->default(0);
            $table->unsignedMediumInteger('number_current_stocks')->default(0);
            $table->unsignedMediumInteger('number_stocks_state_in_process')->default(0);
            $table->unsignedMediumInteger('number_stocks_state_active')->default(0);
            $table->unsignedMediumInteger('number_stocks_state_discontinuing')->default(0);
            $table->unsignedMediumInteger('number_stocks_state_discontinued')->default(0);
            $table->unsignedMediumInteger('number_stocks_state_suspended')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('trade_unit_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_stocks',
                'number_current_stocks',
                'number_stocks_state_in_process',
                'number_stocks_state_active',
                'number_stocks_state_discontinuing',
                'number_stocks_state_discontinued',
                'number_stocks_state_suspended',
            ]);
        });
    }
};
