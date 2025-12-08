<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Mar 2023 20:59:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('mailshots', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('subject')->index();

            $table->unsignedSmallInteger('outbox_id')->nullable()->index();
            $table->foreign('outbox_id')->references('id')->on('outboxes');
            $table->unsignedInteger('email_id')->nullable()->index();
            $table->foreign('email_id')->references('id')->on('emails');
            $table->string('state')->index()->default(MailshotStateEnum::IN_PROCESS->value);
            $table->string('type')->index();

            $table->dateTimeTz('date')->index();
            $table->dateTimeTz('ready_at')->nullable();
            $table->dateTimeTz('scheduled_at')->nullable();
            $table->dateTimeTz('recipients_stored_at')->nullable();
            $table->dateTimeTz('start_sending_at')->nullable();
            $table->dateTimeTz('sent_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->dateTimeTz('stopped_at')->nullable();
            $table->jsonb('recipients_recipe');
            $table->unsignedSmallInteger('publisher_id')->nullable();
            $table->foreign('publisher_id')->references('id')->on('users');

            $table->jsonb('data');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table = $this->softDeletes($table);
            $table->string('source_id')->nullable()->unique();
            $table->string('source_alt_id')->nullable()->unique();
            $table->string('source_alt2_id')->nullable()->unique();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailshots');
    }
};
