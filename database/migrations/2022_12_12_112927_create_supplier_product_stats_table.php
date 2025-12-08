<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Apr 2023 13:17:48 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasProcurementStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasProcurementStats;

    public function up(): void
    {
        Schema::create('supplier_product_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('supplier_product_id')->index();
            $table->foreign('supplier_product_id')->references('id')->on('supplier_products');

            $table->unsignedInteger('number_historic_supplier_products')->default(0);
            $table = $this->purchaseOrdersStats($table);
            $table = $this->stockDeliveriesStats($table);
            $table = $this->purchaseOrderTransactionsStats($table);
            $table = $this->stockDeliveryItemsStats($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_stats');
    }
};
