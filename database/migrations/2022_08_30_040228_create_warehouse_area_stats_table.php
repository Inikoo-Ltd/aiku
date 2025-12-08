<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:40:42 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasFulfilmentStats;
use App\Stubs\Migrations\HasInventoryStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasFulfilmentStats;
    use HasInventoryStats;

    public function up(): void
    {
        Schema::create('warehouse_area_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('warehouse_area_id')->index();
            $table->foreign('warehouse_area_id')->references('id')->on('warehouse_areas')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->locationsStats($table);
            $table = $this->fulfilmentAssetsStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_area_stats');
    }
};
