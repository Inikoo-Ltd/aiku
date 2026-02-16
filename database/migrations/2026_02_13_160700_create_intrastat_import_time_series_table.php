<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Thu, 13 Feb 2026 16:07:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Stubs\Migrations\HasTimeSeries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasTimeSeries;

    public function up(): void
    {
        Schema::create('intrastat_import_time_series', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
            $table->string('tariff_code')->index()->comment('Part tariff code');
            $table->unsignedSmallInteger('country_id')->comment('Supplier country');
            $table->foreign('country_id')->references('id')->on('countries')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('tax_category_id')->nullable();
            $table->foreign('tax_category_id')->references('id')->on('tax_categories')->onUpdate('cascade')->onDelete('cascade');
            $this->getTimeSeriesFields($table);

            $table->unique(
                ['organisation_id', 'tariff_code', 'country_id', 'tax_category_id', 'frequency', 'from', 'to'],
                'intrastat_import_ts_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intrastat_import_time_series');
    }
};
