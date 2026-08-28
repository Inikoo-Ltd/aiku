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
        Schema::create('chat_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_message_id');
            $table->foreign('chat_message_id')->references('id')->on('chat_messages')->cascadeOnDelete();
            $table->unsignedBigInteger('chat_session_id');
            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->cascadeOnDelete();
            $table->string('reactor_type', 16);
            $table->unsignedBigInteger('reactor_id')->nullable();
            $table->string('emoji', 16);
            $table->timestampsTz();

            $table->index(['chat_message_id', 'reactor_type', 'reactor_id']);
            $table->unique(['chat_message_id', 'reactor_type', 'reactor_id', 'emoji'], 'chat_message_reactions_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_reactions');
    }
};
