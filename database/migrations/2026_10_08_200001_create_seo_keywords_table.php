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
        Schema::create('seo_keywords', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->string('keyword', 255);
            $table->string('country_code', 2);
            $table->string('language_code', 8);
            $table->unsignedBigInteger('avg_monthly_searches')->nullable();
            $table->jsonb('monthly_searches')->nullable();
            $table->string('competition', 16)->nullable();
            $table->unsignedSmallInteger('competition_index')->nullable();
            $table->decimal('cpc', 10, 2)->nullable();
            $table->decimal('low_top_of_page_bid', 10, 2)->nullable();
            $table->decimal('high_top_of_page_bid', 10, 2)->nullable();
            $table->unsignedSmallInteger('keyword_difficulty')->nullable();
            $table->string('intent', 16)->nullable();
            $table->jsonb('secondary_intents')->nullable();
            $table->string('intent_source', 32)->nullable();
            $table->string('source', 32);
            $table->timestampTz('fetched_at');
            $table->timestampsTz();
            $table->unique(['shop_id', 'keyword', 'country_code', 'language_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keywords');
    }
};
