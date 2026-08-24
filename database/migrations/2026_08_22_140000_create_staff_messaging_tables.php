<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_conversations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->ulid()->unique();
            $table->string('type')->index()->default('dm');
            $table->string('name')->nullable();
            $table->string('dm_key')->nullable()->unique()->comment('sorted user ids, only for type dm');
            $table->nullableMorphs('context');
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestampTz('last_message_at')->nullable()->index();
            $table->timestampsTz();
        });

        Schema::create('staff_conversation_participants', function (Blueprint $table) {
            $table->unsignedInteger('staff_conversation_id');
            $table->foreign('staff_conversation_id')->references('id')->on('staff_conversations')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampsTz();
            $table->primary(['staff_conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('staff_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('staff_conversation_id')->index();
            $table->foreign('staff_conversation_id')->references('id')->on('staff_conversations')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->foreign('parent_id')->references('id')->on('staff_messages')->nullOnDelete();
            $table->text('body');
            $table->unsignedSmallInteger('language_id')->nullable();
            $table->foreign('language_id')->references('id')->on('languages');
            $table->unsignedInteger('media_id')->nullable();
            $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('staff_message_translations', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_message_id');
            $table->foreign('staff_message_id')->references('id')->on('staff_messages')->cascadeOnDelete();
            $table->unsignedSmallInteger('language_id');
            $table->foreign('language_id')->references('id')->on('languages');
            $table->text('body');
            $table->timestampsTz();
            $table->primary(['staff_message_id', 'language_id']);
        });

        Schema::create('staff_message_reactions', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_message_id');
            $table->foreign('staff_message_id')->references('id')->on('staff_messages')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestampsTz();
            $table->primary(['staff_message_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_message_reactions');
        Schema::dropIfExists('staff_message_translations');
        Schema::dropIfExists('staff_messages');
        Schema::dropIfExists('staff_conversation_participants');
        Schema::dropIfExists('staff_conversations');
    }
};
