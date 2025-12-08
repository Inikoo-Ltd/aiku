<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Mar 2023 20:59:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('dispatched_emails', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index()->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('outbox_id')->nullable();
            $table->foreign('outbox_id')->references('id')->on('outboxes');

            $table->string('parent_type')->index()->comment('MailShot|EmailBulkRun|EmailPush|EmailOngoingRun');
            $table->unsignedInteger('parent_id');

            $table->unsignedInteger('email_address_id')->nullable();
            $table->foreign('email_address_id')->references('id')->on('email_addresses');
            $table->string('provider')->index();
            $table->string('provider_dispatch_id')->nullable();
            $table->string('recipient_type')->nullable();
            $table->unsignedInteger('recipient_id')->nullable();
            $table->string('state')->default(DispatchedEmailStateEnum::READY->value);
            $table->dateTimeTz('sent_at')->nullable();
            $table->dateTimeTz('first_read_at')->nullable();
            $table->dateTimeTz('last_read_at')->nullable();
            $table->dateTimeTz('first_clicked_at')->nullable();
            $table->dateTimeTz('last_clicked_at')->nullable();
            $table->unsignedSmallInteger('number_reads')->default(0);
            $table->unsignedSmallInteger('number_clicks')->default(0);
            $table->boolean('mask_as_spam')->default(false);
            $table->boolean('provoked_unsubscribe')->default(false);
            $table->jsonb('data');
            $table->boolean('is_test')->default(false)->index();
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
            $table->index(['provider', 'provider_dispatch_id']);
            $table->index(['parent_type', 'parent_id']);
            $table->index(['recipient_type', 'recipient_id']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatched_emails');
    }
};
