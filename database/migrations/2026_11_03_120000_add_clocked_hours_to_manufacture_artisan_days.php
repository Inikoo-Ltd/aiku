<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('manufacture_artisan_days', function (Blueprint $table) {
            $table->unsignedInteger('clocked_minutes')->nullable()->comment('timesheet working time, clocked breaks left out; null when not clocked');
            $table->unsignedInteger('tablet_over_clocked_minutes')->default(0);
            $table->unsignedSmallInteger('open_clockings')->default(0)->comment('clocked in and not out');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_agency_worker')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_artisan_days', function (Blueprint $table) {
            $table->dropColumn(['clocked_minutes', 'tablet_over_clocked_minutes', 'open_clockings']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('is_agency_worker');
        });
    }
};
