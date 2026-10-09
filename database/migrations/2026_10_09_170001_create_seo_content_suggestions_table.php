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
        Schema::create('seo_content_suggestions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('website_id');
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->unsignedInteger('webpage_id');
            $table->foreign('webpage_id')->references('id')->on('webpages')->cascadeOnDelete();
            $table->string('field', 16);
            $table->text('current_value')->nullable();
            $table->text('suggestion');
            $table->string('reason', 48);
            $table->string('state', 16)->default('pending');
            $table->string('model', 128)->nullable();
            $table->unsignedSmallInteger('requested_by_user_id')->nullable();
            $table->foreign('requested_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedSmallInteger('decided_by_user_id')->nullable();
            $table->foreign('decided_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->index(['website_id', 'state']);
            $table->index(['webpage_id', 'field', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_content_suggestions');
    }
};
