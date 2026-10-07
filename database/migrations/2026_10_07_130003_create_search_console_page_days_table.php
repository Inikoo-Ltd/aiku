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
        Schema::create('search_console_page_days', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('website_id');
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->unsignedInteger('webpage_id')->nullable();
            $table->foreign('webpage_id')->references('id')->on('webpages')->nullOnDelete();
            $table->date('date');
            $table->text('page_url');
            $table->char('page_url_hash', 32);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('position', 8, 2)->default(0);
            $table->timestampsTz();
            $table->unique(['website_id', 'date', 'page_url_hash']);
            $table->index(['webpage_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_page_days');
    }
};
