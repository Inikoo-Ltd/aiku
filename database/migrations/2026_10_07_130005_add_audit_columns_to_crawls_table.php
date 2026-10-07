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
        Schema::table('crawls', function (Blueprint $table) {
            $table->unsignedInteger('max_pages')->nullable();
            $table->decimal('health_score', 5, 2)->nullable();
            $table->unsignedInteger('pages_with_errors')->default(0);
            $table->unsignedInteger('number_errors')->default(0);
            $table->unsignedInteger('number_warnings')->default(0);
            $table->unsignedInteger('number_notices')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('crawls', function (Blueprint $table) {
            $table->dropColumn(['max_pages', 'health_score', 'pages_with_errors', 'number_errors', 'number_warnings', 'number_notices']);
        });
    }
};
