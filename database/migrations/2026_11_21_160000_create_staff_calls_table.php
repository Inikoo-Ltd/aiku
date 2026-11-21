<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_calls', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedInteger('staff_conversation_id')->index();
            $table->foreign('staff_conversation_id')->references('id')->on('staff_conversations')->cascadeOnDelete();
            $table->unsignedInteger('started_by_user_id')->index();
            $table->foreign('started_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('room', 64)->unique()->comment('LiveKit room name, random and never shown in broadcasts');
            $table->timestampTz('started_at');
            $table->timestampTz('answered_at')->nullable();
            $table->timestampTz('ended_at')->nullable()->index();
            $table->string('outcome')->nullable()->comment('answered, declined, missed, cancelled');
            $table->unsignedInteger('declined_by_user_id')->nullable();
            $table->foreign('declined_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX staff_calls_one_live_per_conversation ON staff_calls (staff_conversation_id) WHERE ended_at IS NULL');

        Schema::create('staff_call_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_call_id');
            $table->foreign('staff_call_id')->references('id')->on('staff_calls')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestampTz('joined_at');
            $table->timestampTz('left_at')->nullable();
            $table->timestampTz('last_seen_at');
            $table->timestampsTz();
            $table->unique(['staff_call_id', 'user_id']);
        });

        Schema::table('staff_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_call_id')->nullable()->index();
            $table->foreign('staff_call_id')->references('id')->on('staff_calls')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_messages', function (Blueprint $table) {
            $table->dropForeign(['staff_call_id']);
            $table->dropColumn('staff_call_id');
        });
        Schema::dropIfExists('staff_call_participants');
        Schema::dropIfExists('staff_calls');
    }
};
