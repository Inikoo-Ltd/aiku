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
        Schema::create('seo_domain_overviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('domain', 255);
            $table->string('country_code', 2);
            $table->string('language_code', 8);
            $table->date('date');
            $table->unsignedInteger('organic_keywords')->default(0);
            $table->decimal('estimated_traffic', 14, 2)->default(0);
            $table->unsignedInteger('top_3')->default(0);
            $table->unsignedInteger('top_10')->default(0);
            $table->unsignedInteger('stored_keywords')->default(0);
            $table->timestampsTz();
            $table->unique(['domain', 'country_code', 'language_code', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_domain_overviews');
    }
};
