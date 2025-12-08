<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jan 2024 16:15:37 Malaysia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Fulfilment\FulfilmentCustomer\FulfilmentCustomerStatusEnum;
use App\Stubs\Migrations\HasFulfilmentStats;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasFulfilmentStats;
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;

    public function up(): void
    {
        if (! Schema::hasTable('fulfilment_customers')) {
            Schema::create('fulfilment_customers', function (Blueprint $table) {
                $table->increments('id');
                $table = $this->groupOrgRelationship($table);
                $table->string('status')->default(FulfilmentCustomerStatusEnum::NO_RENTAL_AGREEMENT->value)->index();
                $table->string('slug')->unique()->collation('und_ns');
                $table->unsignedInteger('customer_id');
                $table->foreign('customer_id')->references('id')->on('customers');
                $table->unsignedInteger('fulfilment_id');
                $table->foreign('fulfilment_id')->references('id')->on('fulfilments');
                $table->boolean('pallets_storage')->default(true);
                $table->boolean('items_storage')->default(false);
                $table->boolean('dropshipping')->default(false);
                $table->string('webhook_access_key')->nullable()->index();
                $table->unsignedInteger('current_recurring_bill_id')->nullable()->index();
                $table = $this->fulfilmentStats($table);
                $table->jsonb('data');
                $table->timestampsTz();
                $this->softDeletes($table);
                $table->string('source_id')->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfilment_customers');
    }
};
