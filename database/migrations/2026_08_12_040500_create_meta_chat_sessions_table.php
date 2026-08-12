<?php

use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('meta_chat_sessions', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedSmallInteger('meta_channel_id')->index();
            $table->foreign('meta_channel_id')->references('id')->on('meta_channels')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade');

            $table->unsignedInteger('web_user_id')->index()->nullable();
            $table->foreign('web_user_id')->references('id')->on('web_users')->onUpdate('cascade')->onDelete('cascade');

            $table->ulid()->unique();
            $table->string('status')->default(ChatSessionStatusEnum::ACTIVE->value);
            $table->string('guest_identifier', 255)->nullable()->comment('Random alias we use to identify the guest');
            $table->string('ai_model_version', 50)->nullable();

            $table->unsignedSmallInteger('language_id')->index()->default(68);
            $table->foreign('language_id')->references('id')->on('languages');

            $table->smallInteger('active_user_language_id')->nullable();
            $table->foreign('active_user_language_id', 'meta_chat_sessions_active_user_lang_fk')->references('id')->on('languages');

            $table->smallInteger('user_language_id')->nullable();
            $table->foreign('user_language_id', 'meta_chat_sessions_user_lang_fk')->references('id')->on('languages');

            $table->smallInteger('agent_language_id')->nullable()->default(68);
            $table->foreign('agent_language_id', 'meta_chat_sessions_agent_lang_fk')->references('id')->on('languages');

            $table->string('priority')->index()->default(ChatPriorityEnum::NORMAL->value);

            $table->unsignedSmallInteger('rating')->nullable();

            $table->string('closed_by')->nullable();

            $table->json('metadata')->nullable();
            $table->char('geo_country_code', 5)->nullable();
            $table->unsignedBigInteger('website_visitor_id')->nullable()->index();

            $table->timestampTz('last_visitor_message_at')->nullable();
            $table->timestampTz('last_agent_message_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $this->softDeletes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_chat_sessions');
    }
};
