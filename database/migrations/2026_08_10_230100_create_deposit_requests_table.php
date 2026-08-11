<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:01:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('deposit_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('agent_id')->index();
            $table->foreign('agent_id')->references('id')->on('agents');
            $table->string('reference')->nullable();
            $table->unsignedSmallInteger('currency_id')->index();
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->string('state')->index()->default(DepositRequestStateEnum::REQUESTED->value);
            $table->dateTimeTz('requested_at');
            $table->dateTimeTz('settled_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('deposit_request_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('deposit_request_id')->index();
            $table->foreign('deposit_request_id')->references('id')->on('deposit_requests');
            $table->unsignedInteger('aspo_deposit_id')->index();
            $table->foreign('aspo_deposit_id')->references('id')->on('aspo_deposits');
            $table->unsignedInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->decimal('amount', 16);
            $table->decimal('exchange', 16, 6)->default(1);
            $table->dateTimeTz('paid_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_request_items');
        Schema::dropIfExists('deposit_requests');
    }
};
