<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_turn_readings', function (Blueprint $table) {
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
            $table->unsignedInteger('customer_message_id')->nullable();

            $table->text('customer_wrote')->nullable();
            $table->jsonb('suggested')->nullable();
            $table->string('branch')->nullable()->index();
            $table->string('topic')->nullable();
            $table->unsignedSmallInteger('guides')->default(0);
            $table->boolean('facts')->default(false);
            $table->boolean('engineer')->default(false);
            $table->string('engineer_ticket')->nullable();
            $table->string('next_step')->nullable();
            $table->string('used')->nullable()->index();
            $table->timestampTz('used_at')->nullable();
            $table->unsignedInteger('reply_message_id')->nullable();
            $table->text('reply')->nullable();
            $table->timestampTz('replied_at')->nullable();

            $table->timestampsTz();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_turn_readings');
    }
};
