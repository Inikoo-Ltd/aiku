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
        Schema::create('org_stock_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('organisation_stock_history_id')->index();
            $table->foreign('organisation_stock_history_id')->references('id')->on('organisation_stock_histories');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('org_stock_id')->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks');
            $table->date('date')->index();
            $table->float('quantity_in_locations')->default(0)->comment('Stock at the end of the day, min value zero');
            $table->decimal('org_stock_value', 16)->default(0)->comment('FIFO method');
            $table->decimal('grp_stock_value', 16)->default(0)->comment('FIFO method');
            $table->decimal('org_stock_commercial_value', 16)->default(0);
            $table->decimal('grp_stock_commercial_value', 16)->default(0);
            $table->integer('number_locations')->default(0);
            $table->float('unit_value')->nullable();
            $table->timestampsTz();
            $table->unique(['date', 'organisation_id', 'org_stock_id']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('org_stock_histories');
    }
};
