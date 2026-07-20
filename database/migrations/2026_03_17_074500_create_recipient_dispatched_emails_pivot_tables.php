<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Mar 2026 07:25:19 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('web_user_has_dispatched_emails', function (Blueprint $table) {
            $table->unsignedInteger('web_user_id')->index();
            $table->foreign('web_user_id', 'wu_hde_web_user_id_foreign')->references('id')->on('web_users')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('dispatched_email_id');
            $table->foreign('dispatched_email_id', 'wu_hde_email_id_foreign')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('customer_has_dispatched_emails', function (Blueprint $table) {
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id', 'c_hde_customer_id_foreign')->references('id')->on('customers')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('dispatched_email_id');
            $table->foreign('dispatched_email_id', 'c_hde_email_id_foreign')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('prospect_has_dispatched_emails', function (Blueprint $table) {
            $table->unsignedInteger('prospect_id')->index();
            $table->foreign('prospect_id', 'p_hde_prospect_id_foreign')->references('id')->on('prospects')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('dispatched_email_id');
            $table->foreign('dispatched_email_id', 'p_hde_email_id_foreign')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('external_subscriber_email_recipient_has_dispatched_emails', function (Blueprint $table) {
            $table->unsignedSmallInteger('external_subscriber_email_recipient_id')->index();
            $table->foreign('external_subscriber_email_recipient_id', 'ese_hde_recipient_id_foreign')->references('id')->on('external_subscriber_email_recipients')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('dispatched_email_id');
            $table->foreign('dispatched_email_id', 'ese_hde_email_id_foreign')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('test_email_recipient_has_dispatched_emails', function (Blueprint $table) {
            $table->unsignedSmallInteger('test_email_recipient_id')->index();
            $table->foreign('test_email_recipient_id', 'te_hde_recipient_id_foreign')->references('id')->on('test_email_recipients')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('dispatched_email_id');
            $table->foreign('dispatched_email_id', 'te_hde_email_id_foreign')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
        });

        Schema::create('chat_email_recipient_has_dispatched_emails', function (Blueprint $table) {
            $table->unsignedBigInteger('chat_email_recipient_id')->index();
            $table->foreign('chat_email_recipient_id', 'ce_hde_recipient_id_foreign')->references('id')->on('chat_email_recipients')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('dispatched_email_id');
            $table->foreign('dispatched_email_id', 'ce_hde_email_id_foreign')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_email_recipient_has_dispatched_emails');
        Schema::dropIfExists('test_email_recipient_has_dispatched_emails');
        Schema::dropIfExists('external_subscriber_email_recipient_has_dispatched_emails');
        Schema::dropIfExists('prospect_has_dispatched_emails');
        Schema::dropIfExists('customer_has_dispatched_emails');
        Schema::dropIfExists('web_user_has_dispatched_emails');
    }
};
