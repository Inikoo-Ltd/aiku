<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 07 Nov 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('manufacture_task_sessions', 'mix_use_by')) {
            Schema::table('manufacture_task_sessions', function (Blueprint $table) {
                $table->dropColumn('mix_use_by');
            });
        }

        if (Schema::hasColumn('artefacts_manufacture_tasks', 'mix_shelf_life_days')) {
            Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
                $table->dropColumn('mix_shelf_life_days');
            });
        }
    }

    public function down(): void
    {
        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->timestampTz('mix_use_by')->nullable();
        });

        Schema::table('artefacts_manufacture_tasks', function (Blueprint $table) {
            $table->unsignedSmallInteger('mix_shelf_life_days')->nullable();
        });
    }
};
