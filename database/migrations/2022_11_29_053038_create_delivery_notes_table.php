<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 25 Aug 2022 13:13:46 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSalesTransactionParents;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    use HasSalesTransactionParents;

    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->unique()->collation('und_ns');
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');

            $table = $this->salesTransactionParents($table);

            $table->string('reference')->index();
            $table->string('type')->default(DeliveryNoteTypeEnum::ORDER->value)->index();

            $table->string('state')->index()->default(DeliveryNoteStateEnum::UNASSIGNED->value);

            $table->boolean('can_dispatch')->nullable();
            $table->boolean('restocking')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->boolean('delivery_locked')->default(false);

            $table->unsignedInteger('address_id')->index()->nullable();
            $table->foreign('address_id')->references('id')->on('addresses');
            $table->unsignedInteger('delivery_country_id')->index()->nullable();
            $table->foreign('delivery_country_id')->references('id')->on('countries');

            $table->decimal('weight', 16)->nullable()->default(0);
            $table->unsignedSmallInteger('number_stocks')->default(0);
            $table->unsignedSmallInteger('number_picks')->default(0);

            $table->boolean('has_out_of_stocks')->default(false);

            $table->decimal('picking_percentage', 5, 2)->default(0);
            $table->decimal('packing_percentage', 5, 2)->default(0);

            $table->unsignedSmallInteger('picker_id')->nullable()->index()->comment('Main picker');
            $table->foreign('picker_id')->references('id')->on('employees');
            $table->unsignedSmallInteger('packer_id')->nullable()->index()->comment('Main packer');
            $table->foreign('packer_id')->references('id')->on('employees');

            $table->dateTimeTz('date')->index();

            $table->dateTimeTz('queued_at')->nullable();
            $table->dateTimeTz('handling_at')->nullable();
            $table->dateTimeTz('handling_blocked_at')->nullable();
            $table->dateTimeTz('packed_at')->nullable();
            $table->dateTimeTz('finalised_at')->nullable();
            $table->dateTimeTz('dispatched_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();

            $table->dateTimeTz('start_picking')->nullable();
            $table->dateTimeTz('end_picking')->nullable();
            $table->dateTimeTz('start_packing')->nullable();
            $table->dateTimeTz('end_packing')->nullable();

            $table->dateTimeTz('picking_on_hold_time')->nullable()->comment('Time when picking was put on hold (seconds)');
            $table->dateTimeTz('packing_on_hold_time')->nullable()->comment('Time when packing was put on hold (seconds)');

            $table->jsonb('data');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
