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
        Schema::create('seo_ai_answers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('prompt_id');
            $table->foreign('prompt_id')->references('id')->on('seo_ai_prompts')->cascadeOnDelete();
            $table->string('platform', 16);
            $table->date('date');
            $table->string('model', 64)->nullable();
            $table->text('answer')->nullable();
            $table->boolean('is_mentioned')->default(false);
            $table->unsignedSmallInteger('brand_position')->nullable();
            $table->boolean('is_cited')->default(false);
            $table->jsonb('brands')->nullable();
            $table->jsonb('competitors')->nullable();
            $table->jsonb('fan_out_queries')->nullable();
            $table->text('check_url')->nullable();
            $table->timestampsTz();
            $table->unique(['prompt_id', 'platform', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_ai_answers');
    }
};
