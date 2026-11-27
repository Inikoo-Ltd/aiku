<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_meetings', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->string('code', 32)->unique()->comment('Random code in the /meet link; the only thing the link carries');
            $table->string('title', 120)->nullable();
            $table->unsignedInteger('created_by_user_id')->index();
            $table->foreign('created_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->boolean('for_one_call')->default(false)->comment('Link copied from a running call: it ends with that call');
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('last_used_at');
            $table->timestampTz('ended_at')->nullable()->index();
            $table->timestampsTz();
        });

        Schema::table('staff_calls', function (Blueprint $table) {
            $table->unsignedInteger('staff_conversation_id')->nullable()->change();
            $table->unsignedBigInteger('staff_meeting_id')->nullable()->index();
            $table->foreign('staff_meeting_id')->references('id')->on('staff_meetings')->nullOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX staff_calls_one_live_per_meeting ON staff_calls (staff_meeting_id) WHERE ended_at IS NULL');

        Schema::create('staff_meeting_guests', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->unsignedBigInteger('staff_meeting_id')->index();
            $table->foreign('staff_meeting_id')->references('id')->on('staff_meetings')->cascadeOnDelete();
            $table->unsignedBigInteger('staff_call_id')->nullable()->index()->comment('The call they were let into');
            $table->foreign('staff_call_id')->references('id')->on('staff_calls')->nullOnDelete();
            $table->string('name', 60);
            $table->string('secret_hash', 64)->comment('sha256 of the secret only the guest browser holds');
            $table->string('status', 16)->index()->comment('waiting, admitted, denied, removed, left');
            $table->ipAddress('ip')->nullable();
            $table->unsignedInteger('decided_by_user_id')->nullable();
            $table->foreign('decided_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('last_seen_at');
            $table->timestampTz('left_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_meeting_guests');
        DB::statement('DROP INDEX IF EXISTS staff_calls_one_live_per_meeting');
        Schema::table('staff_calls', function (Blueprint $table) {
            $table->dropForeign(['staff_meeting_id']);
            $table->dropColumn('staff_meeting_id');
        });
        Schema::dropIfExists('staff_meetings');
    }
};
