<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_ai_drafts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();

            $table->unsignedInteger('chat_session_id')->nullable()->index();
            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->cascadeOnDelete();
            $table->unsignedInteger('meta_chat_session_id')->nullable()->index();
            $table->foreign('meta_chat_session_id')->references('id')->on('meta_chat_sessions')->cascadeOnDelete();
            $table->unsignedInteger('trigger_message_id')->nullable();

            $table->string('topic')->index();
            $table->jsonb('facts');
            $table->text('text');

            $table->string('status')->index()->default(ChatAiDraftStatusEnum::PENDING->value);
            $table->timestampTz('taken_at')->nullable();
            $table->unsignedInteger('reply_message_id')->nullable();
            $table->unsignedSmallInteger('decided_by_user_id')->nullable()->index();
            $table->foreign('decided_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();

            $table->timestampsTz();
        });

        // One draft waiting per conversation: a newer one replaces it, so staff never choose
        // between two answers to the same question.
        foreach (['chat_session_id', 'meta_chat_session_id'] as $column) {
            DB::statement(
                "CREATE UNIQUE INDEX chat_ai_drafts_one_pending_per_{$column}
                 ON chat_ai_drafts ({$column})
                 WHERE status = '".ChatAiDraftStatusEnum::PENDING->value."' AND {$column} IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_ai_drafts');
    }
};
