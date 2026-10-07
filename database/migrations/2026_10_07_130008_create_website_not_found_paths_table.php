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
        Schema::create('website_not_found_paths', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('website_id');
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->text('path');
            $table->char('path_hash', 32);
            $table->string('last_segment')->index();
            $table->unsignedInteger('hits')->default(0);
            $table->text('last_referrer')->nullable();
            $table->boolean('is_ignored')->default(false);
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at')->index();
            $table->timestampsTz();
            $table->unique(['website_id', 'path_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_not_found_paths');
    }
};
