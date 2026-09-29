<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 04:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_archive_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();

            $table->string('gmail_message_id');
            $table->string('gmail_thread_id')->index();
            $table->boolean('is_outbound')->index();
            $table->string('from_address')->nullable()->index();
            $table->jsonb('to_addresses')->nullable();
            $table->string('counterpart_address')->nullable()->index();
            $table->string('subject', 1000)->nullable();
            $table->text('text')->nullable();
            $table->timestampTz('sent_at')->index();
            $table->timestampsTz();
            $table->unique(['shop_id', 'gmail_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_archive_messages');
    }
};
