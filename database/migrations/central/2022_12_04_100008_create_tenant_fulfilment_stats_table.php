<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Mar 2023 23:08:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::create('tenant_fulfilment_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedInteger('number_customers_with_stocks')->default(0);
            $table->unsignedInteger('number_customers_with_active_stocks')->default(0);
            $table->unsignedInteger('number_customers_with_assets')->default(0);
            $table->unsignedInteger('number_assets')->default(0);

            $table->timestampsTz();
        });
    }


    public function down()
    {
        Schema::dropIfExists('tenant_fulfilment_stats');
    }
};
