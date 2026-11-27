<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_help_grants', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedInteger('granter_user_id')->index()->comment('The person being helped: their access is lent');
            $table->foreign('granter_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('helper_user_id')->index();
            $table->foreign('helper_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('staff_conversation_id')->nullable()->index();
            $table->foreign('staff_conversation_id')->references('id')->on('staff_conversations')->nullOnDelete();
            $table->unsignedSmallInteger('minutes');
            $table->timestampTz('granted_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('ended_at')->nullable();
            $table->string('end_reason', 32)->nullable()->comment('ended_by_granter, stopped_by_helper, helper_logged_out, account_deactivated, replaced, expired');
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX staff_help_grants_one_open_per_granter ON staff_help_grants (granter_user_id) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_help_grants');
    }
};
