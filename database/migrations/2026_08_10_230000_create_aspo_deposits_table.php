<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\SupplyChain\AspoDeposit\AspoDepositStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('aspo_deposits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('agent_id')->index();
            $table->foreign('agent_id')->references('id')->on('agents');
            $table->unsignedInteger('agent_supplier_purchase_order_id')->index();
            $table->foreign('agent_supplier_purchase_order_id', 'aspo_deposits_aspo_id_foreign')->references('id')->on('agent_supplier_purchase_orders');
            $table->string('reference')->nullable();
            $table->decimal('amount', 16);
            $table->unsignedSmallInteger('currency_id')->index();
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->string('state')->index()->default(AspoDepositStateEnum::PENDING->value);
            $table->dateTimeTz('paid_to_supplier_at')->nullable();
            $table->dateTimeTz('refunded_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aspo_deposits');
    }
};
