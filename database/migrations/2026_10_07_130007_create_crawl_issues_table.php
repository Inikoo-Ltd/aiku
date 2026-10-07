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
        Schema::create('crawl_issues', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('crawl_id');
            $table->foreign('crawl_id')->references('id')->on('crawls')->cascadeOnDelete();
            $table->unsignedBigInteger('crawl_page_id');
            $table->foreign('crawl_page_id')->references('id')->on('crawl_pages')->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('severity', 16);
            $table->jsonb('details')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->index(['crawl_id', 'type']);
            $table->index('crawl_page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_issues');
    }
};
