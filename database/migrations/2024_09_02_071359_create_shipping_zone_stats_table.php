<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Sept 2024 16:55:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('shipping_zone_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedInteger('shipping_zone_id')->index();
            $table->foreign('shipping_zone_id')->references('id')->on('shipping_zones');
            $table->timestampTz('first_used_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->unsignedInteger('number_shipping_zones')->default(0);
            $table->unsignedInteger('number_customers')->default(0);
            $table->unsignedInteger('number_orders')->default(0);
            $table->decimal('amount', 18)->default(0);
            $table->decimal('org_amount', 18)->default(0);
            $table->decimal('grp_amount', 18)->default(0);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_stats');
    }
};
