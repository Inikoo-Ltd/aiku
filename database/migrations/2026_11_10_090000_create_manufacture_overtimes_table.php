<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('manufacture_overtimes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table->unsignedSmallInteger('employee_id')->index();
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->unsignedSmallInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users');
            $table->date('date')->index();
            $table->timestampTz('planned_start')->nullable();
            $table->timestampTz('planned_end')->nullable();
            $table->string('decision');
            $table->string('compensation')->nullable()->comment('paid or toil, when approved');
            $table->decimal('toil_ratio', 4, 2)->nullable()->comment('TOIL hours given per overtime hour, as set when decided');
            $table->text('reason')->nullable();
            $table->unsignedSmallInteger('decided_clocked_minutes')->nullable()->comment('clocked time when decided; null when planned ahead');
            $table->unsignedSmallInteger('decided_by_user_id')->nullable();
            $table->foreign('decided_by_user_id')->references('id')->on('users');
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->unique(['production_id', 'employee_id', 'date']);
        });

        Schema::create('manufacture_toil_taken', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table->unsignedSmallInteger('employee_id')->index();
            $table->foreign('employee_id')->references('id')->on('employees');
            $table->date('date')->index();
            $table->unsignedSmallInteger('minutes');
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('recorded_by_user_id')->nullable();
            $table->foreign('recorded_by_user_id')->references('id')->on('users');
            $table->timestampsTz();
        });

        Schema::table('manufacture_artisan_days', function (Blueprint $table) {
            $table->timestampTz('clocked_in_at')->nullable();
            $table->timestampTz('clocked_out_at')->nullable();
            $table->unsignedBigInteger('manufacture_overtime_id')->nullable()->index();
            $table->foreign('manufacture_overtime_id')->references('id')->on('manufacture_overtimes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_artisan_days', function (Blueprint $table) {
            $table->dropForeign(['manufacture_overtime_id']);
            $table->dropColumn(['clocked_in_at', 'clocked_out_at', 'manufacture_overtime_id']);
        });
        Schema::dropIfExists('manufacture_toil_taken');
        Schema::dropIfExists('manufacture_overtimes');
    }
};
