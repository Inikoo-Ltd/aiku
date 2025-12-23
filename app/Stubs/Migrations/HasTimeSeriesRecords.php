<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasTimeSeriesRecords
{
    public function getTimeSeriesRecordsField(Blueprint $table): Blueprint
    {
        $table->dateTimeTz('from')->nullable()->index();
        $table->dateTimeTz('to')->nullable()->index();
        $table->timestampsTz();

        return $table;
    }
}
