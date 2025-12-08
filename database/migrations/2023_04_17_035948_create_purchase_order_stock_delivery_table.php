<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_stock_delivery', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('purchase_order_id')->index();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders');
            $table->unsignedInteger('stock_delivery_id')->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries');
            $table->timestampsTz();
            $table->unique(['purchase_order_id', 'stock_delivery_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_stock_delivery');
    }
};
