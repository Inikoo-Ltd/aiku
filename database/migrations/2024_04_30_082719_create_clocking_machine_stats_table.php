<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 09:27:29 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
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
        Schema::create('clocking_machine_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('clocking_machine_id')->nullable()->index();
            $table->foreign('clocking_machine_id')->references('id')->on('clocking_machines');
            $table = $this->getClockingsFieldStats($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clocking_machine_stats');
    }
};
