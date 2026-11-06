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
        Schema::table('crawls', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->unsignedSmallInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->jsonb('config')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crawls', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['name', 'user_id', 'config']);
        });
    }
};
