<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_message_translations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('chat_message_id');
            $table->foreign('chat_message_id', 'cmt_message_fk')
                ->references('id')
                ->on('chat_messages')
                ->onDelete('cascade');

            $table->smallInteger('target_language_id');
            $table->foreign('target_language_id', 'cmt_language_fk')
                ->references('id')
                ->on('languages');

            $table->text('translated_text');
            $table->timestampsTz();
            $table->unique(['chat_message_id', 'target_language_id'], 'chat_message_translations_unique');
            $table->index(['chat_message_id', 'target_language_id'], 'cmt_message_lang_idx');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('chat_message_translations');
    }
};
