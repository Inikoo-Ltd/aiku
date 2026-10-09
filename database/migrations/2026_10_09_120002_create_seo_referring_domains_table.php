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
        Schema::create('seo_referring_domains', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('domain', 255);
            $table->string('referring_domain', 255);
            $table->unsignedSmallInteger('rank')->nullable();
            $table->unsignedBigInteger('backlinks')->default(0);
            $table->boolean('is_own_website')->default(false);
            $table->timestampTz('first_seen')->nullable();
            $table->timestampTz('first_fetched_at');
            $table->timestampTz('last_fetched_at');
            $table->timestampTz('lost_at')->nullable();
            $table->timestampsTz();
            $table->unique(['domain', 'referring_domain']);
            $table->index(['domain', 'lost_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_referring_domains');
    }
};
