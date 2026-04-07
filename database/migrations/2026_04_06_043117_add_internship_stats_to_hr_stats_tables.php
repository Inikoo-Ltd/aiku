<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Apr 2026 12:30:00 Malaysia Time
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $tables = [
            'job_position_stats',
            'group_human_resources_stats',
            'organisation_human_resources_stats',
            'workplace_stats'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'number_employees_type_internship')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedSmallInteger('number_employees_type_internship')->default(0);
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'job_position_stats',
            'group_human_resources_stats',
            'organisation_human_resources_stats',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'number_employees_type_internship')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('number_employees_type_internship');
                });
            }
        }
    }
};
