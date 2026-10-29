<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_inbox_timings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('measure', 24)->comment('inbox_open, conversation_open, reply_sent or freeze');
            $table->string('channel', 16)->nullable()->comment('website, email or whatsapp, for conversation_open and reply_sent');
            $table->boolean('is_full_load')->nullable()->comment('inbox_open only: true when the browser loaded the whole app, false when moving to the inbox from another page');
            $table->unsignedInteger('duration_ms');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['measure', 'created_at']);
        });

        DB::statement("COMMENT ON TABLE chat_inbox_timings IS 'Customer Inbox speed measured in the staff browsers (INI-145), one row per measurement: inbox_open from the click (or page start) until the conversation list is painted, conversation_open from opening a chat until its messages are painted, reply_sent from Send until the server accepted it, freeze for each time the page could not respond for 200 ms or more. Report the 90th percentile, e.g. percentile_cont(0.9) WITHIN GROUP (ORDER BY duration_ms)'");
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_inbox_timings');
    }
};
