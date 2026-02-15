<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Feb 2026 00:23:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_human_resources_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_clocking_machines_type_qr_code')->default(0);
        });
        Schema::table('organisation_human_resources_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_clocking_machines_type_qr_code')->default(0);
        });
        Schema::table('workplace_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_clocking_machines_type_qr_code')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('group_human_resources_stats', function (Blueprint $table) {
            $table->dropColumn('number_clocking_machines_type_qr_code');
        });
        Schema::table('organisation_human_resources_stats', function (Blueprint $table) {
            $table->dropColumn('number_clocking_machines_type_qr_code');
        });
        Schema::table('workplace_stats', function (Blueprint $table) {
            $table->dropColumn('number_clocking_machines_type_qr_code');
        });
    }
};
