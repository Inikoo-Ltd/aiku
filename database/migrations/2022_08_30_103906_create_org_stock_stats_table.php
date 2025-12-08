<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 10:24:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasInventoryStats;
use App\Stubs\Migrations\HasProcurementStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasInventoryStats;
    use HasProcurementStats;

    public function up(): void
    {
        Schema::create('org_stock_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('org_stock_id')->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedSmallInteger('number_locations')->default(0);

            $table = $this->orgStocksMovementsStats($table);

            $table = $this->purchaseOrdersStats($table);
            $table = $this->stockDeliveriesStats($table);
            $table = $this->purchaseOrderTransactionsStats($table);
            $table = $this->stockDeliveryItemsStats($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_stock_stats');
    }
};
