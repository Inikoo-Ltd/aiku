<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 14:32:38 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('supplier_delivery_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('supplier_delivery_id')->index();
            $table->foreign('supplier_delivery_id')->references('id')->on('supplier_deliveries');
            $table->unsignedInteger('supplier_product_id')->index();
            $table->foreign('supplier_product_id')->references('id')->on('supplier_products');
            $table->jsonb('data');
            $table->decimal('unit_quantity');
            $table->decimal('unit_price');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_delivery_items');
    }
};
