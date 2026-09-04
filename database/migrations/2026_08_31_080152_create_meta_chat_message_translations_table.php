<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('meta_chat_message_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_chat_message_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('target_language_id')->unsigned();
            $table->text('translated_text');
            $table->timestampsTz();

            // One translation per language, so asking twice refreshes rather than stacks.
            $table->unique(['meta_chat_message_id', 'target_language_id'], 'meta_chat_message_translation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_chat_message_translations');
    }
};
