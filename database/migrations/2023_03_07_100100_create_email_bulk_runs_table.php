<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Nov 2024 10:24:34 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('email_bulk_runs', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('subject')->index();
            $table->unsignedSmallInteger('outbox_id')->nullable()->index();
            $table->foreign('outbox_id')->references('id')->on('outboxes');

            $table->unsignedSmallInteger('email_ongoing_run_id')->nullable()->index();
            $table->foreign('email_ongoing_run_id')->references('id')->on('email_ongoing_runs');

            $table->unsignedInteger('email_id')->nullable()->index();
            $table->foreign('email_id')->references('id')->on('emails');

            $table->unsignedInteger('snapshot_id')->nullable()->index();
            $table->foreign('snapshot_id')->references('id')->on('snapshots');

            $table->string('state')->index();

            $table->dateTimeTz('scheduled_at')->nullable();
            $table->dateTimeTz('recipients_stored_at')->nullable();
            $table->dateTimeTz('start_sending_at')->nullable();
            $table->dateTimeTz('sent_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->dateTimeTz('stopped_at')->nullable();

            $table->jsonb('data');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_bulk_runs');
    }
};
