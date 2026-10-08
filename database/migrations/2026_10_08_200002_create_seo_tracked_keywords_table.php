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
        Schema::create('seo_tracked_keywords', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->string('keyword', 255);
            $table->string('country_code', 2);
            $table->string('language_code', 8);
            $table->string('device', 16);
            $table->string('frequency', 16);
            $table->unsignedInteger('target_webpage_id')->nullable();
            $table->foreign('target_webpage_id')->references('id')->on('webpages')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->unique(['shop_id', 'keyword', 'country_code', 'language_code', 'device']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_tracked_keywords');
    }
};
