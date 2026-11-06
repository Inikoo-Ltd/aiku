<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('crawl_schedules', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('website_id')->unique();
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->string('frequency', 16);
            $table->unsignedSmallInteger('weekday')->nullable();
            $table->unsignedSmallInteger('day_of_month')->nullable();
            $table->unsignedSmallInteger('hour');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('last_run_at')->nullable();
            $table->timestampTz('next_run_at')->nullable()->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_schedules');
    }
};
