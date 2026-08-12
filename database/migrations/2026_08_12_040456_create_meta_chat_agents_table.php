<?php

use App\Enums\CRM\Livechat\ChatAgentPresenceStatusEnum;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('meta_chat_agents', function (Blueprint $table) {
            $table->smallIncrements('id');

            $table->unsignedSmallInteger('meta_channel_id')->index();
            $table->foreign('meta_channel_id')->references('id')->on('meta_channels')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedSmallInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->unique(['meta_channel_id', 'user_id'], 'meta_chat_agents_channel_user_unique');

            $table->unsignedSmallInteger('max_concurrent_chats')->default(10);
            $table->boolean('is_online')->index()->default(false);
            $table->boolean('is_available')->index()->default(false);

            $table->unsignedSmallInteger('current_chat_count')->default(0);
            $table->json('specialization')->nullable();
            $table->boolean('auto_accept')->default(true);

            $table->smallInteger('language_id')->nullable()->default(68);
            $table->foreign('language_id', 'meta_chat_agents_language_fk')->references('id')->on('languages');

            $table->string('presence_status')->default(ChatAgentPresenceStatusEnum::OFFLINE->value)->index();
            $table->timestampTz('last_heartbeat_at')->nullable()->index();
            $table->timestampTz('last_activity_at')->nullable();

            $table->timestampsTz();
            $this->softDeletes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_chat_agents');
    }
};
