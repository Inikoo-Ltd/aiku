<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Apr 2023 13:18:07 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasOrderFields;
use App\Stubs\Migrations\HasProcurementOrderFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    use HasOrderFields;
    use HasProcurementOrderFields;

    public function up(): void
    {
        Schema::create('purchase_order_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('purchase_order_id')->index();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders');

            $table = $this->procurementItemFields($table);

            $table->string('state')->index()->default(PurchaseOrderTransactionStateEnum::IN_PROCESS->value);
            $table->string('delivery_state')->index()->default(PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS->value);

            $table->decimal('quantity_ordered', 16, 3)->nullable();
            $table->decimal('quantity_dispatched', 16, 3)->default(0)->nullable();
            $table->decimal('quantity_fail', 16, 3)->default(0)->nullable();
            $table->decimal('quantity_cancelled', 16, 3)->default(0)->nullable();

            $table->decimal('net_amount', 16)->default(0);
            $table->decimal('grp_net_amount', 16)->nullable();
            $table->decimal('org_net_amount', 16)->nullable();
            $table->decimal('grp_exchange', 16, 4)->nullable();
            $table->decimal('org_exchange', 16, 4)->nullable();

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
        Schema::dropIfExists('purchase_order_transactions');
    }
};
