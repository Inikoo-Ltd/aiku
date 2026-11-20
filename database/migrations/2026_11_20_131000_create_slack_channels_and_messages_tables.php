<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 13:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('slack_channels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->string('slack_id')->unique();
            $table->string('name')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_member')->default(false);
            $table->jsonb('data')->nullable();
            $table->string('history_backfilled_to')->nullable()->comment('oldest message ts reached by the first backfill');
            $table->timestampTz('history_synced_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('slack_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('slack_channel_id');
            $table->foreign('slack_channel_id')->references('id')->on('slack_channels')->cascadeOnDelete();
            $table->string('ts');
            $table->string('thread_ts')->nullable();
            $table->string('slack_user_id')->nullable()->index();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('subtype')->nullable();
            $table->text('text')->nullable();
            $table->timestampTz('sent_at')->index();
            $table->timestampTz('edited_at')->nullable();
            $table->jsonb('data')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['slack_channel_id', 'ts']);
            $table->index(['slack_channel_id', 'thread_ts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_messages');
        Schema::dropIfExists('slack_channels');
    }
};
