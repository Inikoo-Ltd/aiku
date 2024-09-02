<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Jul 2024 13:35:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('charge_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('charge_id')->index();
            $table->foreign('charge_id')->references('id')->on('charges');


            $table->unsignedInteger('number_historic_assets')->default(0);

            $table->timestampTz('first_used_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();

            $table->unsignedInteger('number_customers')->default(0);
            $table->unsignedInteger('number_orders')->default(0);
            $table->decimal('amount')->default(0);
            $table->decimal('org_amount')->default(0);
            $table->decimal('group_amount')->default(0);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('charge_stats');
    }
};
