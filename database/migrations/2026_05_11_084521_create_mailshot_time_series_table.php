<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 11 May 2026 16:47:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */


use App\Stubs\Migrations\HasTimeSeries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasTimeSeries;

    public function up(): void
    {
        Schema::create('mailshot_time_series', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mailshot_id');
            $table->foreign('mailshot_id')->references('id')->on('mailshots')->onUpdate('cascade')->onDelete('cascade');
            $this->getTimeSeriesFields($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailshot_time_series');
    }
};
