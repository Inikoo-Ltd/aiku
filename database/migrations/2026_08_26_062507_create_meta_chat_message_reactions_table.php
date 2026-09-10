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
        Schema::create('meta_chat_message_reactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('meta_chat_message_id')->index();
            $table->unsignedInteger('meta_chat_session_id')->index();
            $table->string('reactor_type');
            $table->unsignedInteger('reactor_id')->nullable();
            $table->string('emoji');
            $table->string('meta_message_id')->nullable()->comment('wamid of the reaction itself');
            $table->timestampsTz();

            $table->unique(['meta_chat_message_id', 'reactor_type', 'reactor_id'], 'meta_chat_message_reactor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_chat_message_reactions');
    }
};
