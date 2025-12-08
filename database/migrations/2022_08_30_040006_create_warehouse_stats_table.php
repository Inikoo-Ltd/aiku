<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 12:01:35 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

use App\Stubs\Migrations\HasFulfilmentStats;
use App\Stubs\Migrations\HasInventoryStats;
use App\Stubs\Migrations\HasOrderingStats;
use App\Stubs\Migrations\HasSalesStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasFulfilmentStats;
    use HasInventoryStats;
    use HasInventoryStats;
    use HasOrderingStats;
    use HasSalesStats;

    public function up(): void
    {
        Schema::create('warehouse_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onUpdate('cascade')->onDelete('cascade');

            $table = $this->warehousesAreasStats($table);
            $table = $this->locationsStats($table);
            $table = $this->orgStocksMovementsStats($table);
            $table = $this->orgStocksAuditStats($table);

            $table->unsignedSmallInteger('number_fulfilments')->default(0);
            $table = $this->fulfilmentCustomersStats($table);
            $table = $this->fulfilmentStats($table);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stats');
    }
};
