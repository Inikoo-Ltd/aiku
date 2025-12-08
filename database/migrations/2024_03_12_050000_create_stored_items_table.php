<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 06 Dec 2022 12:25:05 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
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
        if (! Schema::hasTable('stored_items')) {
            Schema::create('stored_items', function (Blueprint $table) {
                $table->increments('id');
                $table = $this->groupOrgRelationship($table);
                $table->string('slug')->unique()->collation('und_ns');
                $table->string('reference')->index()->collation('und_ci');
                $table->string('state')->index()->default(StoredItemStateEnum::IN_PROCESS->value);
                $table->unsignedInteger('fulfilment_id')->index();
                $table->foreign('fulfilment_id')->references('id')->on('fulfilments');
                $table->unsignedInteger('fulfilment_customer_id')->index();
                $table->foreign('fulfilment_customer_id')->references('id')->on('fulfilment_customers');
                $table->string('notes');
                $table->boolean('return_requested')->default(false);
                $table->dateTimeTz('received_at')->nullable();
                $table->dateTimeTz('booked_in_at')->nullable();
                $table->dateTimeTz('settled_at')->nullable();
                $table->jsonb('data');
                $table->jsonb('incident_report');
                $table->timestampsTz();
                $table->string('source_id')->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_items');
    }
};
