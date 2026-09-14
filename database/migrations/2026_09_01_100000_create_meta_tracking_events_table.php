<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('meta_tracking_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('meta_chat_session_id')->nullable()->index();
            $table->foreign('meta_chat_session_id')->references('id')->on('meta_chat_sessions')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedBigInteger('meta_chat_message_id')->nullable()->index();
            $table->foreign('meta_chat_message_id')->references('id')->on('meta_chat_messages')->nullOnDelete();

            $table->string('meta_message_id')->nullable()->index()->comment('wamid from the Meta API, kept for statuses arriving before the message is stored');
            $table->string('type');
            $table->jsonb('data');
            $table->timestampTz('created_at')->nullable();
            $table->string('source_id')->nullable()->unique()->comment('Idempotency key, Meta retries webhooks');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_tracking_events');
    }
};
