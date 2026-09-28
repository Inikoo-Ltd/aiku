<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('supplier_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('supplier_id')->nullable();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->unsignedInteger('org_supplier_id')->nullable();
            $table->foreign('org_supplier_id')->references('id')->on('org_suppliers')->nullOnDelete();
            $table->unsignedSmallInteger('org_agent_id')->nullable();
            $table->foreign('org_agent_id')->references('id')->on('org_agents')->nullOnDelete();
            $table->unsignedSmallInteger('org_partner_id')->nullable();
            $table->foreign('org_partner_id')->references('id')->on('org_partners')->nullOnDelete();
            $table->unsignedInteger('purchase_order_id')->nullable()->index();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->unsignedBigInteger('dispatched_email_id')->nullable()->index();
            $table->foreign('dispatched_email_id')->references('id')->on('dispatched_emails')->nullOnDelete();
            $table->string('channel')->default('email')->index();
            $table->string('gmail_message_id')->nullable()->unique();
            $table->string('whatsapp_message_id')->nullable()->unique();
            $table->string('phone_number')->nullable()->index();
            $table->string('delivery_state')->nullable();
            $table->string('gmail_thread_id')->nullable()->index();
            $table->string('header_message_id')->nullable();
            $table->text('header_references')->nullable();
            $table->unsignedSmallInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('direction');
            $table->string('routed_by')->nullable();
            $table->string('from_address')->nullable()->index();
            $table->string('from_name')->nullable();
            $table->jsonb('to')->default('[]');
            $table->jsonb('cc')->default('[]');
            $table->text('subject')->nullable();
            $table->text('snippet')->nullable();
            $table->text('body_text')->nullable();
            $table->text('body_html')->nullable();
            $table->jsonb('attachments')->default('[]');
            $table->timestampTz('sent_at')->index();
            $table->timestampsTz();
            $table->index(['organisation_id', 'sent_at']);
            $table->index(['org_supplier_id', 'sent_at']);
            $table->index(['supplier_id', 'sent_at']);
            $table->index(['org_agent_id', 'sent_at']);
            $table->index(['org_partner_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_messages');
    }
};
