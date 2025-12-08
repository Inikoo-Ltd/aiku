<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Nov 2024 17:36:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Ordering\PurgedOrder\PurgedOrderStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purge_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('purge_id')->index();
            $table->foreign('purge_id')->references('id')->on('purges');

            $table->unsignedMediumInteger('estimated_number_orders')->default(0);
            $table->unsignedMediumInteger('estimated_number_transactions')->default(0);

            $table->unsignedInteger('number_purged_orders')->default(0);

            foreach (PurgedOrderStatusEnum::cases() as $case) {
                $table->unsignedInteger("number_purged_orders_status_{$case->snake()}")->default(0);
            }

            $table->unsignedInteger('number_purged_transactions')->default(0);

            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');

            $table->decimal('estimated_net_amount', 18)->default(0);
            $table->decimal('estimated_org_net_amount', 18)->default(0);
            $table->decimal('estimated_grp_net_amount', 18)->default(0);

            $table->decimal('purged_net_amount', 18)->default(0);
            $table->decimal('purged_org_net_amount', 18)->default(0);
            $table->decimal('purged_grp_net_amount', 18)->default(0);

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purge_stats');
    }
};
