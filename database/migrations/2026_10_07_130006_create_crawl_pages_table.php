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
        Schema::create('crawl_pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('crawl_id');
            $table->foreign('crawl_id')->references('id')->on('crawls')->cascadeOnDelete();
            $table->unsignedInteger('webpage_id')->nullable()->index();
            $table->foreign('webpage_id')->references('id')->on('webpages')->nullOnDelete();
            $table->text('url');
            $table->char('url_hash', 32);
            $table->unsignedSmallInteger('depth')->default(0);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('redirect_to')->nullable();
            $table->unsignedSmallInteger('redirect_hops')->default(0);
            $table->unsignedInteger('response_ms')->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->string('content_type', 100)->nullable();
            $table->text('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('canonical')->nullable();
            $table->string('robots_meta')->nullable();
            $table->unsignedSmallInteger('h1_count')->default(0);
            $table->unsignedSmallInteger('images_without_alt')->default(0);
            $table->unsignedInteger('inlinks')->default(0);
            $table->boolean('is_in_sitemap')->default(false);
            $table->boolean('is_indexable')->default(false);
            $table->text('fetch_error')->nullable();
            $table->timestampsTz();
            $table->unique(['crawl_id', 'url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_pages');
    }
};
