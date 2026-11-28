<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_message_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('slack_channel_id')->nullable()->index();
            $table->foreign('slack_channel_id')->references('id')->on('slack_channels')->cascadeOnDelete();
            $table->unsignedInteger('staff_conversation_id')->nullable()->index();
            $table->foreign('staff_conversation_id')->references('id')->on('staff_conversations')->cascadeOnDelete();
            $table->string('thread_ts')->nullable()->comment('Slack thread the message replies in');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('Verba message the message replies to');
            $table->foreign('parent_id')->references('id')->on('staff_messages')->nullOnDelete();
            $table->text('text');
            $table->boolean('also_to_channel')->default(false);
            $table->boolean('notify_everyone')->default(false);
            $table->timestampTz('send_at');
            $table->string('state', 16)->default('pending')->comment('pending, sending, sent, failed, cancelled');
            $table->text('failure_reason')->nullable();
            $table->unsignedBigInteger('slack_message_id')->nullable();
            $table->foreign('slack_message_id')->references('id')->on('slack_messages')->nullOnDelete();
            $table->unsignedBigInteger('staff_message_id')->nullable();
            $table->foreign('staff_message_id')->references('id')->on('staff_messages')->nullOnDelete();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
            $table->index(['state', 'send_at']);
        });

        Schema::create('staff_message_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('slack_message_id')->nullable()->index();
            $table->foreign('slack_message_id')->references('id')->on('slack_messages')->cascadeOnDelete();
            $table->unsignedBigInteger('staff_message_id')->nullable()->index();
            $table->foreign('staff_message_id')->references('id')->on('staff_messages')->cascadeOnDelete();
            $table->timestampTz('remind_at');
            $table->timestampTz('rung_at')->nullable();
            $table->timestampsTz();
            $table->index(['rung_at', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_message_reminders');
        Schema::dropIfExists('staff_message_schedules');
    }
};
