<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Jun 2023 03:40:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_trackers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('workplace_id')->nullable()->index();
            $table->foreign('workplace_id')->references('id')->on('workplaces');
            $table->unsignedInteger('timesheet_id')->nullable()->index();
            $table->foreign('timesheet_id')->references('id')->on('timesheets');
            $table->string('subject_type')->comment('Employee|Guest');
            $table->unsignedSmallInteger('subject_id');
            $table->string('status')->index();
            $table->dateTimeTz('starts_at')->nullable();
            $table->dateTimeTz('ends_at')->nullable();
            $table->unsignedBigInteger('start_clocking_id')->nullable()->index();
            $table->foreign('start_clocking_id')->references('id')->on('clockings');
            $table->unsignedBigInteger('end_clocking_id')->nullable()->index();
            $table->foreign('end_clocking_id')->references('id')->on('clockings');
            $table->unsignedInteger('duration')->nullable()->comment('seconds');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['subject_type', 'subject_id']);

        });

        Schema::table('clockings', function (Blueprint $table) {
            $table->foreign('time_tracker_id')->references('id')->on('time_trackers');
        });
    }

    public function down(): void
    {
        Schema::table('clockings', function (Blueprint $table) {
            $table->dropForeign('clockings_time_tracker_id_foreign');
        });
        Schema::dropIfExists('time_trackers');
    }
};
