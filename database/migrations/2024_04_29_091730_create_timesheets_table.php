<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 12:33:43 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->date('date');
            $table->string('subject_type')->comment('Employee|Guest');
            $table->unsignedSmallInteger('subject_id');
            $table->string('subject_name')->index();
            $table->dateTimeTz('start_at')->nullable();
            $table->dateTimeTz('end_at')->nullable();
            $table->unsignedSmallInteger('number_time_trackers')->default(0);
            $table->unsignedSmallInteger('number_open_time_trackers')->default(0);
            $table->unsignedInteger('working_duration')->default(0)->comment('seconds');
            $table->unsignedInteger('breaks_duration')->default(0)->comment('seconds');
            $table->unsignedInteger('total_duration')->default(0)->comment('seconds');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->index()->nullable();
            $table->unique(['date', 'subject_type', 'subject_id']);

        });

        Schema::table('clockings', function (Blueprint $table) {
            $table->foreign('timesheet_id')->references('id')->on('timesheets');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
