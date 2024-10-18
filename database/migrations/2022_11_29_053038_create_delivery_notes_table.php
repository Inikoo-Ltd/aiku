<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 25 Aug 2022 13:13:46 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->unique()->collation('und_ns');
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedInteger('customer_client_id')->nullable()->index()->after('customer_id');
            $table->foreign('customer_client_id')->references('id')->on('customer_clients');
            $table->string('reference')->index();
            $table->string('type')->default(DeliveryNoteTypeEnum::ORDER->value)->index();

            $table->string('state')->index();
            $table->string('status')->index();

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


            $table->unsignedSmallInteger('picker_id')->nullable()->index()->comment('Main picker');
            $table->foreign('picker_id')->references('id')->on('employees');
            $table->unsignedSmallInteger('packer_id')->nullable()->index()->comment('Main packer');
            $table->foreign('packer_id')->references('id')->on('employees');

            $table->dateTimeTz('date')->index();

            $table->dateTimeTz('submitted_at')->nullable();
            $table->dateTimeTz('in_queue_at')->nullable();
            $table->dateTimeTz('picker_assigned_at')->nullable();
            $table->dateTimeTz('picking_at')->nullable();
            $table->dateTimeTz('picked_at')->nullable();

            $table->dateTimeTz('packing_at')->nullable();
            $table->dateTimeTz('packed_at')->nullable();
            $table->dateTimeTz('finalised_at')->nullable();
            $table->dateTimeTz('settled_at')->nullable();
            $table->dateTimeTz('dispatched_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();


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
