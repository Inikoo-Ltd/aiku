<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('manufacture_paid_time_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table->boolean('inside_shift_only')->default(true)->comment('pay only the time clocked inside the shift, plus approved overtime');
            $table->unsignedSmallInteger('rounding_minutes')->default(5)->comment('a late clock in is paid from the nearest multiple of this after the shift start; 1 = not rounded');
            $table->date('effective_from');
            $table->unsignedSmallInteger('set_by_user_id')->nullable();
            $table->foreign('set_by_user_id')->references('id')->on('users');
            $table->timestampsTz();
            $table->unique(['production_id', 'effective_from']);
        });

        Schema::table('manufacture_artisan_days', function (Blueprint $table) {
            $table->unsignedInteger('paid_seconds')->nullable()->comment('time paid under the paid time rule; null = the time clocked is paid');
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_artisan_days', function (Blueprint $table) {
            $table->dropColumn('paid_seconds');
        });
        Schema::dropIfExists('manufacture_paid_time_rules');
    }
};
