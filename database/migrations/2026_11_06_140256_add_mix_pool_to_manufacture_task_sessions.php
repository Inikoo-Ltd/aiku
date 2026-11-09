<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->string('mix_pool_key')->nullable()->index()->comment('kg steps (HELP-4562): the mix this session made, shared between every open line that uses it; kg not shared out is the leftover');
            $table->timestampTz('mix_use_by')->nullable()->comment('leftover of this mix is not given to lines after this moment');
        });

        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->unsignedSmallInteger('mix_shelf_life_days')->nullable()->comment('kg steps: days a mix keeps, its leftover is not used after that');
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->dropColumn(['mix_pool_key', 'mix_use_by']);
        });

        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->dropColumn('mix_shelf_life_days');
        });
    }
};
