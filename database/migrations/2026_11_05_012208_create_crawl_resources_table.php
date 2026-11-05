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
        Schema::create('crawl_resources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('crawl_id');
            $table->foreign('crawl_id')->references('id')->on('crawls')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->text('url');
            $table->char('url_hash', 32);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->string('content_type', 100)->nullable();
            $table->text('fetch_error')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->unique(['crawl_id', 'url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_resources');
    }
};
