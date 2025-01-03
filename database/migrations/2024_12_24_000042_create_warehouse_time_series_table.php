<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 17:35:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasTimeSeries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasTimeSeries;

    public function up(): void
    {
        Schema::create('warehouse_time_series', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onUpdate('cascade')->onDelete('cascade');
            $this->getTimeSeriesFields($table);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('warehouse_time_series');
    }
};
