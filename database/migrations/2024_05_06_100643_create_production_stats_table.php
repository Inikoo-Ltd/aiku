<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 12:17:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasManufactureStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasManufactureStats;

    public function up(): void
    {
        Schema::create('production_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table = $this->rawMaterialStats($table);
            $table = $this->manufactureTaskStats($table);
            $table = $this->artefactsStats($table);
            $table = $this->jobOrdersStats($table);
            $table->timestampsTz();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_stats');
    }
};
