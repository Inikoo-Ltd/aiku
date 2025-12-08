<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Apr 2024 16:19:55 British Summer Time, Sheffield, UK
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
        Schema::create('guest_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('guest_id')->nullable()->index();
            $table->foreign('guest_id')->references('id')->on('guests');
            $table = $this->getTimesheetsStats($table);
            $table = $this->getClockingsFieldStats($table);
            $table = $this->getTimeTrackersStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_stats');
    }
};
