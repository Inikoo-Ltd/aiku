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
        Schema::create('seo_api_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('provider', 32);
            $table->string('endpoint', 64);
            $table->unsignedSmallInteger('website_id')->nullable()->index();
            $table->foreign('website_id')->references('id')->on('websites')->nullOnDelete();
            $table->boolean('is_success');
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->decimal('cost', 10, 4)->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('created_at')->index();
            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_api_requests');
    }
};
