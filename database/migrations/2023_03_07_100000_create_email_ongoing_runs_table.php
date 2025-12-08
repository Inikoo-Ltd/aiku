<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 08:14:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Comms\EmailOngoingRun\EmailOngoingRunStatusEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('email_ongoing_runs', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('outbox_id')->nullable()->index();
            $table->foreign('outbox_id')->references('id')->on('outboxes');
            $table->unsignedInteger('email_id')->nullable()->index();
            $table->foreign('email_id')->references('id')->on('emails');
            $table->string('code')->index();
            $table->string('type')->index();
            $table->string('status')->default(EmailOngoingRunStatusEnum::IN_PROCESS)->index();
            $table->jsonb('data');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_ongoing_runs');
    }
};
