<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Aug 2024 10:01:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedInteger('top_up_id')->index()->nullable();
            $table->foreign('top_up_id')->references('id')->on('top_ups');
            $table->unsignedInteger('payment_id')->index()->nullable();
            $table->foreign('payment_id')->references('id')->on('payments');
            $table->string('type')->index();
            $table->dateTimeTz('date')->index();
            $table->decimal('amount', 16, 2);
            $table->decimal('running_amount', 16, 2)->nullable();
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->decimal('grp_exchange', 16, 4)->nullable();
            $table->decimal('org_exchange', 16, 4)->nullable();
            $table->decimal('grp_amount', 16)->nullable();
            $table->decimal('org_amount', 16)->nullable();
            $table->string('notes')->nullable();
            $table->jsonb('data');
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->timestampsTz();
            $table->string('source_id')->index()->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
