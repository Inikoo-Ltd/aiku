<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 12:07:10 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::create('master_shop_time_series', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('master_shop_id');
            $table->foreign('master_shop_id')->references('id')->on('master_shops')->onUpdate('cascade')->onDelete('cascade');
            $this->getTimeSeriesFields($table);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('master_shop_time_series');
    }
};
