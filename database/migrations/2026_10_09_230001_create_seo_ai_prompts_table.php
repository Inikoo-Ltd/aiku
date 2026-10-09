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
        Schema::create('seo_ai_prompts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->string('prompt', 500);
            $table->string('country_code', 2);
            $table->string('language_code', 8);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('queued_at')->nullable();
            $table->date('last_run_at')->nullable();
            $table->timestampsTz();
            $table->unique(['shop_id', 'prompt', 'country_code', 'language_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_ai_prompts');
    }
};
