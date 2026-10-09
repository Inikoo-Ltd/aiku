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
        Schema::create('seo_ai_citations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('answer_id');
            $table->foreign('answer_id')->references('id')->on('seo_ai_answers')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('url');
            $table->string('domain', 255);
            $table->text('title')->nullable();
            $table->unsignedInteger('competitor_id')->nullable();
            $table->foreign('competitor_id')->references('id')->on('seo_competitors')->nullOnDelete();
            $table->unsignedSmallInteger('website_id')->nullable();
            $table->foreign('website_id')->references('id')->on('websites')->nullOnDelete();
            $table->unsignedInteger('webpage_id')->nullable();
            $table->foreign('webpage_id')->references('id')->on('webpages')->nullOnDelete();
            $table->timestampsTz();
            $table->index('answer_id');
            $table->index('website_id');
            $table->index('webpage_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_ai_citations');
    }
};
