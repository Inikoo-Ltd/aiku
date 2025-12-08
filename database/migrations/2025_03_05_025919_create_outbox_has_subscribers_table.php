<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Mar 2025 10:59:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
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
        Schema::create('outbox_has_subscribers', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('outbox_id')->index();
            $table->foreign('outbox_id')->references('id')->on('outboxes')->cascadeOnDelete();
            $table->unsignedSmallInteger('user_id')->index()->nullable()->comment('null if external email is set');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('external_email')->nullable()->index()->comment('null if user_id is set');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_has_subscribers');
    }
};
