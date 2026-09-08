<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('user_time_series_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('user_time_series_id');
            $table->foreign('user_time_series_id')->references('id')->on('user_time_series')->onUpdate('cascade')->onDelete('cascade');
            $table->char('frequency', 1)->index();
            $table->string('period');
            $table->timestampTz('from')->nullable()->index();
            $table->timestampTz('to')->nullable()->index();
            $table->unsignedInteger('number_requests')->default(0);
            $table->unsignedInteger('number_logins')->default(0);
            $table->unsignedSmallInteger('number_active_days')->default(0);
            $table->timestampsTz();
            $table->unique(['user_time_series_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_time_series_records');
    }
};
