<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Dec 2025 00:38:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasTimeSeries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasTimeSeries;
    public function up(): void
    {
        Schema::create('variant_time_series', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('variant_id')->index();
            $table->foreign('variant_id')->references('id')->on('variants')->onDelete('cascade')->onUpdate('cascade');
            $this->getTimeSeriesFields($table);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('variant_time_series');
    }
};
