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
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->unsignedSmallInteger('link_depth')->nullable();
            $table->unsignedSmallInteger('sitemap_count')->default(0);
            $table->text('h1')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->char('content_hash', 32)->nullable();
            $table->bigInteger('content_simhash')->nullable();
            $table->jsonb('facts')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropColumn(['link_depth', 'sitemap_count', 'h1', 'word_count', 'content_hash', 'content_simhash', 'facts']);
        });
    }
};
