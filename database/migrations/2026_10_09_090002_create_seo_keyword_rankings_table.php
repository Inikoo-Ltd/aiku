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
        Schema::create('seo_keyword_rankings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tracked_keyword_id');
            $table->foreign('tracked_keyword_id')->references('id')->on('seo_tracked_keywords')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('position')->nullable();
            $table->text('ranking_url')->nullable();
            $table->unsignedInteger('webpage_id')->nullable();
            $table->foreign('webpage_id')->references('id')->on('webpages')->nullOnDelete();
            $table->jsonb('serp_features')->nullable();
            $table->boolean('in_ai_overview')->default(false);
            $table->unsignedSmallInteger('depth');
            $table->timestampsTz();
            $table->unique(['tracked_keyword_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_rankings');
    }
};
