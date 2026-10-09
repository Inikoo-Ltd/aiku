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
        Schema::create('seo_backlink_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('domain', 255);
            $table->date('date');
            $table->unsignedSmallInteger('rank')->nullable();
            $table->unsignedBigInteger('backlinks')->default(0);
            $table->unsignedInteger('referring_domains')->default(0);
            $table->unsignedInteger('referring_main_domains')->default(0);
            $table->unsignedInteger('broken_backlinks')->default(0);
            $table->unsignedInteger('broken_pages')->default(0);
            $table->unsignedSmallInteger('spam_score')->nullable();
            $table->unsignedInteger('new_referring_domains')->nullable();
            $table->unsignedInteger('lost_referring_domains')->nullable();
            $table->timestampsTz();
            $table->unique(['domain', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_backlink_summaries');
    }
};
