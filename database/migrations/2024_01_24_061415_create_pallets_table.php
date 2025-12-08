<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jan 2024 16:25:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Stubs\Migrations\HasFulfilmentStats;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasFulfilmentStats;
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;

    public function up(): void
    {
        if (! Schema::hasTable('pallets')) {
            Schema::create('pallets', function (Blueprint $table) {
                $table->increments('id');
                $table = $this->groupOrgRelationship($table);
                $table->string('slug')->nullable()->unique()->collation('und_ns');
                $table->string('reference')->nullable()->index()->collation('und_ci');
                $table->string('customer_reference')->nullable()->index()->collation('und_ci');
                $table->unsignedInteger('fulfilment_id')->index();
                $table->foreign('fulfilment_id')->references('id')->on('fulfilments');
                $table->unsignedInteger('fulfilment_customer_id')->index();
                $table->foreign('fulfilment_customer_id')->references('id')->on('fulfilment_customers');
                $table->unsignedSmallInteger('warehouse_id')->index();
                $table->foreign('warehouse_id')->references('id')->on('warehouses');
                $table->unsignedInteger('warehouse_area_id')->nullable()->index();
                $table->foreign('warehouse_area_id')->references('id')->on('warehouse_areas');
                $table->unsignedSmallInteger('rental_id')->nullable()->index();
                $table->unsignedSmallInteger('rental_agreement_clause_id')->nullable()->index();

                $table->unsignedInteger('location_id')->index()->nullable();
                $table->foreign('location_id')->references('id')->on('locations');
                $table->unsignedInteger('pallet_delivery_id')->index()->nullable();
                $table->foreign('pallet_delivery_id')->references('id')->on('pallet_deliveries');
                $table->unsignedInteger('pallet_return_id')->index()->nullable();
                $table->foreign('pallet_return_id')->references('id')->on('pallet_returns');
                $table->string('status')->index()->default(PalletStatusEnum::IN_PROCESS->value);
                $table->string('state')->index()->default(PalletStateEnum::IN_PROCESS->value);
                $table->string('type')->index()->default(PalletTypeEnum::PALLET->value);
                $table->unsignedSmallInteger('current_recurring_bill_id')->nullable()->index();
                $table->dateTimeTz('received_at')->nullable();
                $table->dateTimeTz('booking_in_at')->nullable();
                $table->dateTimeTz('set_as_not_received_at')->nullable();
                $table->dateTimeTz('booked_in_at')->nullable();
                $table->dateTimeTz('storing_at')->nullable();
                $table->dateTimeTz('requested_for_return_at')->nullable();
                $table->dateTimeTz('picking_at')->nullable();
                $table->dateTimeTz('picked_at')->nullable();
                $table->dateTimeTz('set_as_incident_at')->nullable();
                $table->dateTimeTz('dispatched_at')->nullable();
                $table->text('notes')->nullable();

                $table->jsonb('data');
                $table->jsonb('incident_report');

                $table->boolean('with_stored_items')->default(false);
                $table = $this->storedItemsStatsFields($table);

                $table = $this->storedItemsAuditStats($table);
                $table->datetimeTz('fetched_at')->nullable();
                $table->datetimeTz('last_fetched_at')->nullable();
                $table->timestampsTz();
                $table = $this->softDeletes($table);
                $table->string('source_id')->nullable()->unique();
                $table->index(['customer_reference', 'fulfilment_customer_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pallets');
    }
};
