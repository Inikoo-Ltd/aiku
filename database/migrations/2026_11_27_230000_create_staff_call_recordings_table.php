<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_call_recordings', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedBigInteger('staff_call_id')->index();
            $table->foreign('staff_call_id')->references('id')->on('staff_calls')->cascadeOnDelete();
            $table->string('status', 16)->index()->comment('starting, recording, stopped, processing, done, failed');
            $table->unsignedInteger('started_by_user_id')->index();
            $table->foreign('started_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('stopped_by_user_id')->nullable();
            $table->foreign('stopped_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedSmallInteger('keep_audio_months')->default(0)->comment('0: audio deleted once the transcript and summary are saved');
            $table->timestampTz('started_at');
            $table->timestampTz('stopped_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->jsonb('user_ids')->default('[]')->comment('Staff in the call while it was recorded: present at Start, or joined before Stop');
            $table->jsonb('transcript')->nullable()->comment('[{at, speaker, text}], kept forever');
            $table->jsonb('summary')->nullable()->comment('{summary, decisions, action_items}, kept forever');
            $table->timestampTz('delete_audio_at')->nullable()->index();
            $table->timestampTz('audio_deleted_at')->nullable();
            $table->string('error')->nullable();
            $table->timestampsTz();
        });

        DB::statement("CREATE UNIQUE INDEX staff_call_recordings_one_live_per_call ON staff_call_recordings (staff_call_id) WHERE status = 'recording'");

        Schema::create('staff_call_recording_tracks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_call_recording_id')->index();
            $table->foreign('staff_call_recording_id')->references('id')->on('staff_call_recordings')->cascadeOnDelete();
            $table->string('track_sid', 64);
            $table->string('egress_id', 64)->nullable()->unique();
            $table->string('identity', 64)->comment('LiveKit identity: a user id, or guest-{ulid}');
            $table->string('speaker', 120)->comment('Name shown in the transcript');
            $table->string('path')->comment('Object key on the staff-chat-r2 disk');
            $table->string('status', 16)->comment('starting, active, stopping, ended, failed');
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();
            $table->unique(['staff_call_recording_id', 'track_sid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_call_recording_tracks');
        Schema::dropIfExists('staff_call_recordings');
    }
};
