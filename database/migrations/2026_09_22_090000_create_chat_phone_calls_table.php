<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

use App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('chat_phone_calls', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('organisation_id')->nullable()->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->nullOnDelete();
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();

            $table->unsignedSmallInteger('chat_agent_id')->index();
            $table->foreign('chat_agent_id')->references('id')->on('chat_agents')->cascadeOnDelete();
            $table->unsignedSmallInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->string('status')->index()->default(ChatPhoneCallStatusEnum::IN_PROGRESS->value);
            $table->string('contact_type')->nullable()->index();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->unsignedInteger('chat_session_id')->nullable()->index();
            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->nullOnDelete();
            $table->string('contact_name')->nullable();

            $table->text('notes')->nullable();
            $table->timestampTz('started_at')->index();
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->timestampsTz();
        });

        // One call at a time per agent is the whole point of the feature: a second one running
        // would make the timer and "who is on a call" lie, and nothing in the interface could
        // put it right again. Partial index so finished calls stay unconstrained.
        DB::statement(
            "CREATE UNIQUE INDEX chat_phone_calls_one_in_progress_per_agent
             ON chat_phone_calls (chat_agent_id)
             WHERE status = '".ChatPhoneCallStatusEnum::IN_PROGRESS->value."'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_phone_calls');
    }
};
