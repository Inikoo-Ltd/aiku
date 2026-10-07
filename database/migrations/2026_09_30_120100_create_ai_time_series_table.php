<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:01:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasTimeSeries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasTimeSeries;

    public function up(): void
    {
        Schema::create('ai_time_series', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('feature');
            $this->getTimeSeriesFields($table);
            $table->unique(['feature', 'frequency']);
        });

        Schema::create('ai_time_series_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('ai_time_series_id');
            $table->foreign('ai_time_series_id')->references('id')->on('ai_time_series')->onUpdate('cascade')->onDelete('cascade');
            $table->char('frequency', 1)->index();
            $table->string('period');
            $table->timestampTz('from')->nullable()->index();
            $table->timestampTz('to')->nullable()->index();
            $table->unsignedInteger('number_calls')->default(0);
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->decimal('cost', 16, 6)->default(0);
            $table->timestampsTz();
            $table->unique(['ai_time_series_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_time_series_records');
        Schema::dropIfExists('ai_time_series');
    }
};
