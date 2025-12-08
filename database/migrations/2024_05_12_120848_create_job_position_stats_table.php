<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 May 2024 13:09:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasHumanResourcesStats;
use App\Stubs\Migrations\HasSysAdminStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasHumanResourcesStats;
    use HasSysAdminStats;

    public function up(): void
    {
        Schema::create('job_position_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('job_position_id');
            $table->foreign('job_position_id')->references('id')->on('job_positions')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->getEmployeeFieldStats($table);
            $table = $this->guestsStatsFields($table);

            $table->unsignedSmallInteger('number_roles')->default(0);
            $table->double('number_employees_work_time')->default(0);
            $table->double('number_guests_work_time')->default(0);
            $table->decimal('share_work_time', 7, 6)->nullable()->comment('This is the share of the total work time of the employees in this job position');
            $table->decimal('share_work_time_including_guests', 7, 6)->nullable()->comment('This is the share of the total work time of the employees and guests in this job position');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_position_stats');
    }
};
