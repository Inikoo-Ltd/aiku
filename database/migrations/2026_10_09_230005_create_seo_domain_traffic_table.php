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
        Schema::create('seo_domain_traffic', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('domain', 255);
            $table->string('country_code', 2);
            $table->string('language_code', 8);
            $table->date('month');
            $table->decimal('organic_traffic', 14, 2)->default(0);
            $table->unsignedInteger('organic_keywords')->default(0);
            $table->decimal('paid_traffic', 14, 2)->default(0);
            $table->unsignedInteger('paid_keywords')->default(0);
            $table->decimal('featured_snippet_traffic', 14, 2)->default(0);
            $table->decimal('local_pack_traffic', 14, 2)->default(0);
            $table->timestampsTz();
            $table->unique(['domain', 'country_code', 'language_code', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_domain_traffic');
    }
};
