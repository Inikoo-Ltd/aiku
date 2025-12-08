<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Sep 2023 16:09:39 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasHumanResourcesStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasHumanResourcesStats;

    public function up(): void
    {
        Schema::create('group_human_resources_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $this->getJobPositionsStats($table);
            $this->getWorkplaceFieldStats($table);
            $this->getEmployeeFieldStats($table);
            $this->getClockingMachinesFieldStats($table);
            $this->getClockingsFieldStats($table);
            $this->getTimesheetsStats($table);
            $this->getTimeTrackersStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_human_resources_stats');
    }
};
