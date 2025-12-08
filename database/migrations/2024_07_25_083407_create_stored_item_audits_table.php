<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 17:02:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('stored_item_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->ulid()->nullable();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('fulfilment_customer_id');
            $table->foreign('fulfilment_customer_id')->references('id')->on('fulfilment_customers');
            $table->unsignedSmallInteger('fulfilment_id');
            $table->foreign('fulfilment_id')->references('id')->on('fulfilments');
            $table->unsignedSmallInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('reference')->unique()->index();
            $table->string('state')->default(StoredItemAuditStateEnum::IN_PROCESS->value);
            $table->dateTimeTz('in_process_at')->nullable();
            $table->dateTimeTz('completed_at')->nullable();
            $table->text('public_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->jsonb('data')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_item_audits');
    }
};
