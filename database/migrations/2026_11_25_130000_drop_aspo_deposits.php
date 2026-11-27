<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('stock_delivery_deposit_applications');

        if (Schema::hasColumn('deposit_request_items', 'aspo_deposit_id')) {
            Schema::table('deposit_request_items', function (Blueprint $table) {
                $table->dropForeign(['aspo_deposit_id']);
                $table->dropColumn('aspo_deposit_id');
            });
        }

        Schema::dropIfExists('aspo_deposits');
    }

    public function down(): void
    {
        Schema::create('aspo_deposits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('agent_id')->index();
            $table->foreign('agent_id')->references('id')->on('agents');
            $table->unsignedInteger('agent_supplier_purchase_order_id')->nullable()->index();
            $table->foreign('agent_supplier_purchase_order_id', 'aspo_deposits_aspo_id_foreign')->references('id')->on('agent_supplier_purchase_orders');
            $table->unsignedInteger('purchase_order_id')->nullable()->index();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders');
            $table->string('reference')->nullable();
            $table->decimal('amount', 16);
            $table->unsignedSmallInteger('currency_id')->index();
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->string('state')->index()->default('pending');
            $table->dateTimeTz('paid_to_supplier_at')->nullable();
            $table->dateTimeTz('refunded_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::table('deposit_request_items', function (Blueprint $table) {
            $table->unsignedInteger('aspo_deposit_id')->nullable()->index();
            $table->foreign('aspo_deposit_id')->references('id')->on('aspo_deposits');
        });

        Schema::create('stock_delivery_deposit_applications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('aspo_deposit_id')->index();
            $table->foreign('aspo_deposit_id')->references('id')->on('aspo_deposits');
            $table->unsignedInteger('stock_delivery_id')->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries');
            $table->decimal('amount', 16);
            $table->unsignedSmallInteger('created_by')->nullable()->index();
            $table->foreign('created_by')->references('id')->on('users');
            $table->unsignedSmallInteger('deleted_by')->nullable()->index();
            $table->foreign('deleted_by')->references('id')->on('users');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }
};
