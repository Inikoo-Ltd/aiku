<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Apr 2023 13:17:29 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasProcurementStats;
use App\Stubs\Migrations\HasSupplyChainStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasProcurementStats;
    use HasSupplyChainStats;

    public function up(): void
    {
        Schema::create('supplier_stats', function (Blueprint $table) {
            $table->mediumIncrements('id');
            $table->unsignedInteger('supplier_id')->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->supplierProductsStats($table);
            $table = $this->purchaseOrdersStats($table);
            $table = $this->stockDeliveriesStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_stats');
    }
};
