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
        Schema::table('seo_tracked_keywords', function (Blueprint $table) {
            $table->string('pending_task_id', 64)->nullable()->index();
            $table->timestampTz('pending_task_posted_at')->nullable();
            $table->timestampTz('last_checked_at')->nullable()->index();
            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedSmallInteger('previous_position')->nullable();
            $table->timestampTz('previous_checked_at')->nullable();
            $table->text('ranking_url')->nullable();
            $table->unsignedInteger('ranking_webpage_id')->nullable();
            $table->foreign('ranking_webpage_id')->references('id')->on('webpages')->nullOnDelete();
            $table->jsonb('serp_features')->nullable();
            $table->boolean('in_ai_overview')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('seo_tracked_keywords', function (Blueprint $table) {
            $table->dropForeign(['ranking_webpage_id']);
            $table->dropColumn(['pending_task_id', 'pending_task_posted_at', 'last_checked_at', 'position', 'previous_position', 'previous_checked_at', 'ranking_url', 'ranking_webpage_id', 'serp_features', 'in_ai_overview']);
        });
    }
};
