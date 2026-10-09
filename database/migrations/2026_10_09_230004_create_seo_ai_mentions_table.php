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
        Schema::create('seo_ai_mentions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->date('date');
            $table->string('platform', 16);
            $table->unsignedInteger('location_code');
            $table->string('language_code', 8);
            $table->string('domain', 255);
            $table->unsignedInteger('competitor_id')->nullable();
            $table->foreign('competitor_id')->references('id')->on('seo_competitors')->nullOnDelete();
            $table->unsignedInteger('mentions')->default(0);
            $table->unsignedBigInteger('ai_search_volume')->default(0);
            $table->timestampsTz();
            $table->unique(['shop_id', 'platform', 'date', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_ai_mentions');
    }
};
