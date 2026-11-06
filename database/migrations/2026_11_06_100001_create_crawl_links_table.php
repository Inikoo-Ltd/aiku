<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('crawl_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('crawl_id');
            $table->foreign('crawl_id')->references('id')->on('crawls')->cascadeOnDelete();
            $table->char('from_url_hash', 32);
            $table->text('to_url');
            $table->char('to_url_hash', 32);
            $table->string('kind', 16);
            $table->text('anchor')->nullable();
            $table->string('position', 16)->nullable();
            $table->boolean('is_nofollow')->default(false);
            $table->boolean('is_internal')->default(false);
            $table->index(['crawl_id', 'from_url_hash']);
            $table->index(['crawl_id', 'to_url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_links');
    }
};
