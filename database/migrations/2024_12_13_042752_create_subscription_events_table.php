<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Dec 2024 12:28:03 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);

            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops');

            $table->unsignedSmallInteger('outbox_id');
            $table->foreign('outbox_id')->references('id')->on('outboxes');
            $table->string('model_type')->comment('Customer|Prospect');
            $table->unsignedInteger('model_id');
            $table->string('type')->comment('subscribe|unsubscribe');

            $table->string('origin_type')->nullable()->comment('EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)');
            $table->string('origin_id')->nullable();

            $table->jsonb('data');
            $table->timestamp('created_at')->useCurrent();

            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
            $table->string('source_alt_id')->nullable()->unique();

            $table->index(['model_type', 'model_id']);
            $table->index(['origin_type', 'origin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
    }
};
