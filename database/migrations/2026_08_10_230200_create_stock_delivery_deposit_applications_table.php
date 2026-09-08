<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 23:02:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stock_delivery_deposit_applications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('aspo_deposit_id')->index();
            $table->foreign('aspo_deposit_id')->references('id')->on('aspo_deposits');
            $table->unsignedInteger('stock_delivery_id')->index();
            $table->foreign('stock_delivery_id')->references('id')->on('stock_deliveries');
            $table->decimal('amount', 16);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_delivery_deposit_applications');
    }
};
