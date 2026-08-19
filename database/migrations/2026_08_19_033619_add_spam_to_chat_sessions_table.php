<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->boolean('is_spam')->default(false)->index();
            $table->timestampTz('spam_at')->nullable();
            $table->unsignedInteger('spammed_by_agent_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropColumn(['is_spam', 'spam_at', 'spammed_by_agent_id']);
        });
    }
};
