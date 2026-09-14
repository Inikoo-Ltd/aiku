<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('meta_chat_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('meta_channel_id')->index();
            $table->foreign('meta_channel_id')->references('id')->on('meta_channels')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedInteger('meta_chat_session_id')->index()->nullable();
            $table->foreign('meta_chat_session_id')->references('id')->on('meta_chat_sessions')->nullOnDelete();

            $table->string('event_type')->index()->nullable();
            $table->string('actor_type')->index()->nullable();
            $table->unsignedInteger('actor_id')->nullable();
            $table->index(['actor_type', 'actor_id'], 'meta_chat_events_actor_idx');
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_chat_events');
    }
};
