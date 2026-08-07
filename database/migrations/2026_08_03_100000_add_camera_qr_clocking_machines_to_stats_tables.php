<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('group_human_resources_stats', 'number_clocking_machines_type_camera_qr')) {
            Schema::table('group_human_resources_stats', function (Blueprint $table) {
                $table->unsignedSmallInteger('number_clocking_machines_type_camera_qr')->default(0);
            });
        }
        if (!Schema::hasColumn('organisation_human_resources_stats', 'number_clocking_machines_type_camera_qr')) {
            Schema::table('organisation_human_resources_stats', function (Blueprint $table) {
                $table->unsignedSmallInteger('number_clocking_machines_type_camera_qr')->default(0);
            });
        }
        if (!Schema::hasColumn('workplace_stats', 'number_clocking_machines_type_camera_qr')) {
            Schema::table('workplace_stats', function (Blueprint $table) {
                $table->unsignedSmallInteger('number_clocking_machines_type_camera_qr')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('group_human_resources_stats', 'number_clocking_machines_type_camera_qr')) {
            Schema::table('group_human_resources_stats', function (Blueprint $table) {
                $table->dropColumn('number_clocking_machines_type_camera_qr');
            });
        }
        if (Schema::hasColumn('organisation_human_resources_stats', 'number_clocking_machines_type_camera_qr')) {
            Schema::table('organisation_human_resources_stats', function (Blueprint $table) {
                $table->dropColumn('number_clocking_machines_type_camera_qr');
            });
        }
        if (Schema::hasColumn('workplace_stats', 'number_clocking_machines_type_camera_qr')) {
            Schema::table('workplace_stats', function (Blueprint $table) {
                $table->dropColumn('number_clocking_machines_type_camera_qr');
            });
        }
    }
};
