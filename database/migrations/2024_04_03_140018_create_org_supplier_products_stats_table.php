<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 22:51:58 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
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
        Schema::create('org_supplier_product_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('org_supplier_product_id')->index();
            $table->foreign('org_supplier_product_id')->references('id')->on('org_supplier_products');

            $table = $this->purchaseOrdersStats($table);
            $table = $this->stockDeliveriesStats($table);
            $table = $this->purchaseOrderTransactionsStats($table);
            $table = $this->stockDeliveryItemsStats($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_supplier_product_stats');
    }
};
