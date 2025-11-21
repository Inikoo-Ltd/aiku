<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('chat_events', function (Blueprint $table) {
             $table->id();
            $table->unsignedInteger('session_id'); // Foreign key to chat_sessions
            $table->enum('event_type', [
                'open',
                'ai_reply',
                'transfer_request',
                'transfer_accept',
                'transfer_reject',
                'translate_message',
                'close',
                'rating',
                'note'
            ])->nullable(false);
            $table->enum('actor_type', [
                'guest',
                'user',
                'agent',
                'system',
                'ai'
            ])->nullable();
            $table->unsignedInteger('actor_id')->nullable(); // can id from web user, or agent
            $table->json('payload')->nullable();
            $table->timestampsTz(); // created_at & updated_at


            // Foreign key constraint
            $table->foreign('session_id')
                  ->references('id')
                  ->on('chat_sessions')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');


        });
    }


    public function down(): void
    {
        Schema::dropIfExists('chat_events');
    }
};
