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
        Schema::table('website_visitors', function (Blueprint $table) {
            $table->index('first_seen_at');
            $table->index(['website_id', 'first_seen_at']);
            $table->index(['website_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::table('website_visitors', function (Blueprint $table) {
            $table->dropIndex(['website_id', 'last_seen_at']);
            $table->dropIndex(['website_id', 'first_seen_at']);
            $table->dropIndex(['first_seen_at']);
        });
    }
};
