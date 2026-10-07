<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('search_console_website_days', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('website_id');
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->date('date');
            $table->string('country', 3);
            $table->string('device', 16);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('position', 8, 2)->default(0);
            $table->timestampsTz();
            $table->unique(['website_id', 'date', 'country', 'device']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_website_days');
    }
};
