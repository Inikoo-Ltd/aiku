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
        Schema::create('seo_backlinks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('website_id');
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->text('source_url');
            $table->char('source_url_hash', 32);
            $table->string('source_domain', 255);
            $table->text('source_title')->nullable();
            $table->unsignedSmallInteger('domain_rank')->nullable();
            $table->unsignedSmallInteger('page_rank')->nullable();
            $table->boolean('is_own_website')->default(false);
            $table->text('target_url');
            $table->char('target_url_hash', 32);
            $table->text('target_path');
            $table->unsignedInteger('target_webpage_id')->nullable();
            $table->foreign('target_webpage_id')->references('id')->on('webpages')->nullOnDelete();
            $table->text('anchor')->nullable();
            $table->string('link_type', 16)->nullable();
            $table->boolean('is_dofollow')->default(true);
            $table->boolean('is_broken')->default(false);
            $table->unsignedSmallInteger('target_status_code')->nullable();
            $table->timestampTz('first_seen')->nullable();
            $table->timestampTz('last_seen')->nullable();
            $table->timestampTz('lost_at')->nullable();
            $table->timestampTz('fetched_at');
            $table->timestampsTz();
            $table->unique(['website_id', 'source_url_hash', 'target_url_hash']);
            $table->index(['website_id', 'target_path']);
            $table->index(['website_id', 'first_seen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_backlinks');
    }
};
