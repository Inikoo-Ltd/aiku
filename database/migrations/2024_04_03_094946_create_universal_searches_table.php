<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:23:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasSearchFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasSearchFields;

    public function up(): void
    {
        Schema::create('universal_searches', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->nullable()->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->nullable()->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->string('organisation_slug')->nullable();
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('shop_slug')->nullable();
            $table->unsignedSmallInteger('fulfilment_id')->nullable()->index();
            $table->foreign('fulfilment_id')->references('id')->on('fulfilments');
            $table->string('fulfilment_slug')->nullable();
            $table->unsignedSmallInteger('warehouse_id')->nullable()->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->string('warehouse_slug')->nullable();
            $table->unsignedSmallInteger('website_id')->nullable()->index();
            $table->foreign('website_id')->references('id')->on('websites');
            $table->string('website_slug')->nullable();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->string('customer_slug')->nullable();

            return $this->searchFields($table);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('universal_searches');
    }
};
