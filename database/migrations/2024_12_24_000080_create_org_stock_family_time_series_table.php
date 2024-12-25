<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 00:37:21 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::create('org_stock_family_time_series', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('org_stock_family_id')->index();
            $table->foreign('org_stock_family_id')->references('id')->on('org_stock_families');
            $this->getTimeSeriesFields($table);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('org_stock_family_time_series');
    }
};
