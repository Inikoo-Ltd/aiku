<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 22 Sep 2023 14:19:17 Malaysia Time, Pantai Lembeng, Bali, Indonesia
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
        Schema::create('workplace_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('workplace_id');
            $table->foreign('workplace_id')->references('id')->on('workplaces')->onUpdate('cascade')->onDelete('cascade');
            $this->getEmployeeFieldStats($table);
            $this->getClockingMachinesFieldStats($table);
            $this->getClockingsFieldStats($table);
            $this->getTimeTrackersStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workplace_stats');
    }
};
