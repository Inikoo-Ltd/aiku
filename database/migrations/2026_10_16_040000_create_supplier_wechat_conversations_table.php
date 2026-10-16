<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 16 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('supplier_wechat_conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->string('kind');
            $table->string('room_id')->nullable();
            $table->string('staff_wecom_userid')->nullable();
            $table->string('external_userid')->nullable();
            $table->string('name')->nullable();
            $table->string('remark')->nullable();
            $table->string('corp_name')->nullable();
            $table->unsignedInteger('supplier_id')->nullable();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->unsignedInteger('org_supplier_id')->nullable()->index();
            $table->foreign('org_supplier_id')->references('id')->on('org_suppliers')->nullOnDelete();
            $table->unsignedSmallInteger('org_agent_id')->nullable()->index();
            $table->foreign('org_agent_id')->references('id')->on('org_agents')->nullOnDelete();
            $table->unsignedSmallInteger('org_partner_id')->nullable()->index();
            $table->foreign('org_partner_id')->references('id')->on('org_partners')->nullOnDelete();
            $table->unsignedInteger('ticket_project_id')->nullable()->index();
            $table->foreign('ticket_project_id')->references('id')->on('ticket_projects')->nullOnDelete();
            $table->unsignedInteger('ticket_project_milestone_id')->nullable()->index();
            $table->foreign('ticket_project_milestone_id')->references('id')->on('ticket_project_milestones')->nullOnDelete();
            $table->timestampTz('last_message_at')->nullable();
            $table->text('last_message_snippet')->nullable();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'room_id']);
            $table->unique(['organisation_id', 'staff_wecom_userid', 'external_userid']);
            $table->index(['organisation_id', 'last_message_at']);
        });

        Schema::create('supplier_wechat_conversation_reads', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_wechat_conversation_id');
            $table->foreign('supplier_wechat_conversation_id')->references('id')->on('supplier_wechat_conversations')->cascadeOnDelete();
            $table->unsignedSmallInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestampTz('last_read_at');
            $table->primary(['supplier_wechat_conversation_id', 'user_id']);
        });

        Schema::table('supplier_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_wechat_conversation_id')->nullable();
            $table->foreign('supplier_wechat_conversation_id')->references('id')->on('supplier_wechat_conversations')->nullOnDelete();
            $table->string('wechat_message_id')->nullable()->unique();
            $table->index(['supplier_wechat_conversation_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('supplier_messages', function (Blueprint $table) {
            $table->dropIndex(['supplier_wechat_conversation_id', 'sent_at']);
            $table->dropForeign(['supplier_wechat_conversation_id']);
            $table->dropColumn(['supplier_wechat_conversation_id', 'wechat_message_id']);
        });

        Schema::dropIfExists('supplier_wechat_conversation_reads');
        Schema::dropIfExists('supplier_wechat_conversations');
    }
};
