<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('product_development_component_purchase_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_development_id')->index();
            $table->foreign('product_development_id')->references('id')->on('product_developments')->cascadeOnDelete();
            $table->unsignedInteger('trade_unit_id')->index();
            $table->foreign('trade_unit_id')->references('id')->on('trade_units');
            $table->unsignedInteger('purchase_order_id')->index();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->unsignedInteger('linked_by_id')->nullable();
            $table->foreign('linked_by_id')->references('id')->on('users');
            $table->timestampsTz();
            $table->unique(['product_development_id', 'trade_unit_id', 'purchase_order_id'], 'pd_component_purchase_orders_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_development_component_purchase_orders');
    }
};
