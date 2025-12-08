<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Dec 2024 01:37:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Comms\EmailPush\EmailPushExitStatusEnum;
use App\Enums\Comms\EmailPush\EmailPushStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('email_pushes', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');

            $table->unsignedSmallInteger('number_pushed')->default(0);
            $table->unsignedSmallInteger('number_pending_pushes')->index();
            $table->unsignedSmallInteger('number_sent_pushes')->default(0);

            $table->string('state')->default(EmailPushStateEnum::SCHEDULED->value)->index();

            $table->string('recipient_type')->nullable();
            $table->unsignedInteger('recipient_id')->nullable();

            $table->dateTimeTz('next_push_at')->index()->nullable();
            $table->string('next_push_identifier');

            $table->string('exit_status')->default(EmailPushExitStatusEnum::IN_PROCESS->value)->index();
            $table->string('exit_breakpoint')->nullable()->index();

            $table->jsonb('data');
            $table->timestampsTz();

            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->jsonb('sources');
            $table->index(['recipient_type', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_pushes');
    }
};
