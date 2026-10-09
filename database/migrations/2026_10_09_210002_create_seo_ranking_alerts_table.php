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
        Schema::create('seo_ranking_alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('tracked_keyword_id');
            $table->foreign('tracked_keyword_id')->references('id')->on('seo_tracked_keywords')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason', 32);
            $table->unsignedSmallInteger('previous_position')->nullable();
            $table->unsignedSmallInteger('position')->nullable();
            $table->timestampTz('notified_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tracked_keyword_id', 'date']);
            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_ranking_alerts');
    }
};
