<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 17:21:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasTimeSeries
{
    public function getTimeSeriesFields(Blueprint $table): Blueprint
    {
        $table->string('frequency')->index();
        $table->date('from')->nullable()->index();
        $table->date('to')->nullable()->index();
        $table->json('data')->nullable();
        $table->unsignedBigInteger('number_records')->default(0);
        $table->timestampsTz();

        return $table;
    }
}
