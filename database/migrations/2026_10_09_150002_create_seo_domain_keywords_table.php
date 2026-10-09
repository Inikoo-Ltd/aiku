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
        Schema::create('seo_domain_keywords', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('domain', 255);
            $table->string('country_code', 2);
            $table->string('language_code', 8);
            $table->string('keyword', 255);
            $table->unsignedSmallInteger('position');
            $table->text('url')->nullable();
            $table->unsignedBigInteger('search_volume')->nullable();
            $table->decimal('estimated_traffic', 12, 2)->nullable();
            $table->unsignedSmallInteger('keyword_difficulty')->nullable();
            $table->string('intent', 16)->nullable();
            $table->timestampTz('fetched_at');
            $table->timestampsTz();
            $table->unique(['domain', 'country_code', 'language_code', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_domain_keywords');
    }
};
