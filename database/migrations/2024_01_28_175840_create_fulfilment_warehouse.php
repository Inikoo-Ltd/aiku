<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jan 2024 08:34:15 Malaysia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfilment_warehouse', function (Blueprint $table) {
            $table->unsignedInteger('fulfilment_id')->nullable();
            $table->foreign('fulfilment_id')->references('id')->on('fulfilments')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onUpdate('cascade')->onDelete('cascade');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfilment_warehouse');
    }
};
