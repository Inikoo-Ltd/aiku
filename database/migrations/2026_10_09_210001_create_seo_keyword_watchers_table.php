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
        Schema::create('seo_keyword_watchers', function (Blueprint $table) {
            $table->unsignedInteger('tracked_keyword_id');
            $table->foreign('tracked_keyword_id')->references('id')->on('seo_tracked_keywords')->cascadeOnDelete();
            $table->unsignedSmallInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestampTz('created_at')->nullable();
            $table->primary(['tracked_keyword_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_watchers');
    }
};
