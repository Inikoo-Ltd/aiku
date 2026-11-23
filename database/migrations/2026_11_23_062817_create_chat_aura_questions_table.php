<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_aura_questions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->unsignedInteger('chat_session_id')->nullable()->index();
            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->cascadeOnDelete();
            $table->unsignedInteger('meta_chat_session_id')->nullable()->index();
            $table->foreign('meta_chat_session_id')->references('id')->on('meta_chat_sessions')->cascadeOnDelete();
            $table->unsignedSmallInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->text('question');
            $table->text('answer')->nullable();
            $table->jsonb('tool_calls')->default('[]');
            $table->jsonb('flags')->default('[]');
            $table->string('model')->nullable();
            $table->unsignedInteger('model_calls')->default(0);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestampTz('answered_at')->nullable();
            $table->timestampTz('redrafted_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_aura_questions');
    }
};
