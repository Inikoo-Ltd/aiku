<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_message_stories', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedBigInteger('staff_message_id')->nullable()->unique();
            $table->foreign('staff_message_id')->references('id')->on('staff_messages')->cascadeOnDelete();
            $table->unsignedInteger('staff_conversation_id')->nullable()->index();
            $table->foreign('staff_conversation_id')->references('id')->on('staff_conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('slack_message_id')->nullable()->unique();
            $table->foreign('slack_message_id')->references('id')->on('slack_messages')->cascadeOnDelete();
            $table->unsignedInteger('slack_channel_id')->nullable()->index();
            $table->foreign('slack_channel_id')->references('id')->on('slack_channels')->cascadeOnDelete();
            $table->unsignedInteger('author_user_id')->nullable();
            $table->foreign('author_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('source', 16)->default('editor');
            $table->string('category', 32);
            $table->unsignedSmallInteger('importance');
            $table->string('headline');
            $table->text('blurb');
            $table->timestampTz('happened_at')->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_message_stories');
    }
};
