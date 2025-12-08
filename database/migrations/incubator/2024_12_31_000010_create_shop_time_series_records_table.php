<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 17:20:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_time_series_records', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('shop_time_series_id')->index();
            $table->foreign('shop_time_series_id')->references('id')->on('shop_time_series');
            $table->unsignedInteger('registrations')->default(0);
            $table->unsignedInteger('customers_who_order')->default(0);
            $table->unsignedInteger('prospects_who_register')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_crm_time_series');
    }
};
