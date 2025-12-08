<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 May 2024 20:01:08 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasFulfilmentStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasFulfilmentStats;

    public function up(): void
    {
        Schema::create('pallet_return_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('pallet_return_id');
            $table->foreign('pallet_return_id')->references('id')->on('pallet_returns')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->fulfilmentAssetsStats($table);

            $table->unsignedInteger('number_transactions')->default(0);
            $table->unsignedInteger('number_services')->default(0);
            $table->unsignedInteger('number_physical_goods')->default(0);
            $table->decimal('total_physical_goods_price')->default(0);
            $table->decimal('total_services_price')->default(0);
            $table->decimal('total_price')->default(0);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_return_stats');
    }
};
