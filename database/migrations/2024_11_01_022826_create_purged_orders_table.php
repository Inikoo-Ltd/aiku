<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Nov 2024 11:19:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Ordering\PurgedOrder\PurgedOrderStatusEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('purged_orders', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->index()->nullable();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedInteger('purge_id')->index();
            $table->foreign('purge_id')->references('id')->on('purges');
            $table->unsignedInteger('order_id')->nullable();
            $table->string('status')->default(PurgedOrderStatusEnum::IN_PROCESS);
            $table->dateTimeTz('purged_at')->nullable();
            $table->dateTimeTz('order_created_at')->nullable();
            $table->dateTimeTz('order_last_updated_at')->nullable();
            $table->smallInteger('number_transaction')->nullable();
            $table->decimal('net_amount', 18, 2)->nullable()->comment('Net amount of the deleted order');
            $table->decimal('org_net_amount', 18, 2)->nullable();
            $table->decimal('grp_net_amount', 18, 2)->nullable();
            $table->string('error_message')->nullable();
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purge_records');
    }
};
