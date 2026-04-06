<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Mar 2026 18:54:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('location_org_stock_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('org_stock_history_id')->index();
            $table->foreign('org_stock_history_id')->references('id')->on('org_stock_histories');
            $table->unsignedInteger('org_stock_id')->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks');
            $table->unsignedInteger('location_id')->index();
            $table->foreign('location_id')->references('id')->on('locations');
            $table->date('date')->index();
            $table->float('actual_quantity_in_locations')->comment('Stock at en of day, allow negative values');
            $table->float('quantity_in_locations')->comment('Stock at the end of the day, min value zero');
            $table->timestampsTz();
            $table->unique(['date', 'org_stock_id', 'location_id']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('location_org_stock_histories');
    }
};
