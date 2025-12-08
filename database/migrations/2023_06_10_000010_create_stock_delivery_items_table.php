<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Apr 2023 13:18:12 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Procurement\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasProcurementOrderFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    use HasProcurementOrderFields;

    public function up(): void
    {
        Schema::create('stock_delivery_items', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('stock_delivery_id')->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries');

            $table = $this->procurementItemFields($table);

            $table->string('state')->index()->default(StockDeliveryItemStateEnum::IN_PROCESS->value);

            $table->jsonb('data');
            $table->decimal('unit_quantity', 16, 4);
            $table->decimal('unit_quantity_checked', 16, 4)->default(0);
            $table->decimal('unit_quantity_placed', 16, 4)->default(0);
            $table->decimal('net_unit_price', 16, 4)->default(0);
            $table->decimal('gross_unit_price', 16, 4)->default(0);

            $table->decimal('net_amount', 16)->default(0);
            $table->decimal('grp_net_amount', 16)->nullable();
            $table->decimal('org_net_amount', 16)->nullable();

            $table->boolean('is_costed')->default(false)->index();
            $table->decimal('gross_amount', 16)->default(0);
            $table->decimal('grp_gross_amount', 16)->nullable();
            $table->decimal('org_gross_amount', 16)->nullable();

            $table->decimal('grp_exchange', 16, 4)->nullable();
            $table->decimal('org_exchange', 16, 4)->nullable();

            $table->timestampsTz();
            $table->dateTimeTz('dispatched_at')->nullable();
            $table->dateTimeTz('not_received_at')->nullable();
            $table->dateTimeTz('received_at')->nullable();
            $table->dateTimeTz('checked_at')->nullable();
            $table->dateTimeTz('placed_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_delivery_items');
    }
};
