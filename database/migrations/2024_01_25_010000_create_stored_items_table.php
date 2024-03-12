<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 06 Dec 2022 12:25:05 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Enums\Fulfilment\StoredItem\StoredItemStatusEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;
    public function up(): void
    {
        if (!Schema::hasTable('stored_items')) {
            Schema::create('stored_items', function (Blueprint $table) {
                $table->increments('id');
                $table = $this->groupOrgRelationship($table);
                $table->string('slug')->unique()->collation('und_ns');
                $table->string('reference')->index()->collation('und_ci');
                $table->string('status')->default(StoredItemStatusEnum::IN_PROCESS->value);
                $table->string('state')->index()->default(StoredItemStateEnum::IN_PROCESS->value);
                $table->string('type')->index();
                $table->unsignedInteger('fulfilment_customer_id')->index();
                $table->foreign('fulfilment_customer_id')->references('id')->on('fulfilment_customers');
                $table->unsignedInteger('location_id')->index()->nullable();
                $table->foreign('location_id')->references('id')->on('locations');
                $table->decimal('quantity')->default(0);
                $table->decimal('damaged_quantity')->default(0);
                $table->string('notes');
                $table->unsignedInteger('stored_item_return_id')->index();
                $table->foreign('stored_item_return_id')->references('id')->on('stored_item_returns');
                $table->boolean('return_requested')->default(false);
                $table->dateTimeTz('received_at')->nullable();
                $table->dateTimeTz('booked_in_at')->nullable();
                $table->dateTimeTz('settled_at')->nullable();
                $table->jsonb('data');
                $table = $this->softDeletes($table);
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
