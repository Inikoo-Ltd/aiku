<?php

use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('meta_chat_messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('meta_channel_id')->index();
            $table->foreign('meta_channel_id')->references('id')->on('meta_channels')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedInteger('meta_chat_session_id')->index()->nullable();
            $table->foreign('meta_chat_session_id')->references('id')->on('meta_chat_sessions')->nullOnDelete();

            $table->string('message_type')->index()->default(ChatMessageTypeEnum::TEXT->value);

            $table->string('sender_type');
            $table->unsignedInteger('sender_id')->nullable();
            $table->index(['sender_type', 'sender_id'], 'meta_chat_messages_sender_idx');

            $table->text('message_text')->nullable();

            $table->unsignedInteger('media_id')->index()->nullable();
            $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();

            $table->boolean('is_ai_generated')->nullable();
            $table->boolean('is_validated')->nullable();

            $table->boolean('is_read')->index()->default(false);
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('edited_at')->nullable();

            $table->smallInteger('original_language_id')->nullable();
            $table->foreign('original_language_id', 'meta_chat_messages_orig_lang_fk')->references('id')->on('languages');
            $table->text('original_text')->nullable();

            $table->json('metadata')->nullable();

            $table->timestampsTz();
            $this->softDeletes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_chat_messages');
    }
};
