<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {

            $table->increments('id');

            // FOREIGN KEY → chat_sessions.id
            $table->unsignedInteger('session_id');

            $table->foreign('session_id')->references('id')->on('chat_sessions');

            $table->enum('message_type', ['text', 'image', 'file'])->default('text');

             // sender type: source type
            $table->enum('sender_type', ['user', 'guest', 'agent', 'system', 'ai']);

            // sender_id fleksibel → can web_user_id / agent_chat_id (join base on sender type)
            $table->unsignedInteger('sender_id')->nullable();

            $table->text('message_text')->nullable();

            // media file
            $table->unsignedInteger('media_id');
            $table->foreign('media_id')->references('id')->on('media')->onDelete('cascade');;


            $table->boolean('is_read')->default(false);
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
