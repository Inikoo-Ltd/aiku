<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 26 Oct 2022 13:09:12 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::create('agent_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('agent_id')->index();
            $table->foreign('agent_id')->references('id')->on('agents');

            $table->unsignedSmallInteger('number_suppliers')->default(0);
            $table->unsignedSmallInteger('number_active_suppliers')->default(0);

            $table->unsignedSmallInteger('number_products')->default(0)->comment('all excluding discontinued');
            $productStates = ['in-process', 'active', 'no-available', 'discontinuing', 'discontinued'];
            foreach ($productStates as $productState) {
                $table->unsignedInteger('number_products_state_'.str_replace('-', '_', $productState))->default(0);
            }

            $productStockQuantityStatuses = ['surplus', 'optimal', 'low', 'critical', 'out-of-stock', 'no_applicable'];
            foreach ($productStockQuantityStatuses as $productStockQuantityStatus) {
                $table->unsignedInteger('number_products_stock_quantity_status_'.str_replace('-', '_', $productStockQuantityStatus))->default(0);
            }

            $table->unsignedInteger('number_purchase_orders')->default(0);
            $purchaseOrderStates = ['in-process', 'submitted', 'confirmed', 'dispatched', 'delivered', 'cancelled'];
            foreach ($purchaseOrderStates as $purchaseOrderState) {
                $table->unsignedInteger('number_purchase_orders_state_'.str_replace('-', '_', $purchaseOrderState))->default(0);
            }
            $table->unsignedInteger('number_deliveries')->default(0);


            $table->timestampsTz();
        });
    }

    public function down()
    {
        Schema::dropIfExists('agent_stats');
    }
};
