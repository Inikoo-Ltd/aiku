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
        Schema::create('seo_competitor_rankings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tracked_keyword_id');
            $table->foreign('tracked_keyword_id')->references('id')->on('seo_tracked_keywords')->cascadeOnDelete();
            $table->unsignedInteger('competitor_id');
            $table->foreign('competitor_id')->references('id')->on('seo_competitors')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('position')->nullable();
            $table->text('url')->nullable();
            $table->timestampsTz();
            $table->unique(['tracked_keyword_id', 'competitor_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_competitor_rankings');
    }
};
