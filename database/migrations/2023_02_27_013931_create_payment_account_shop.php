<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 15:36:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_account_shop', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('payment_account_id')->index();
            $table->foreign('payment_account_id')->references('id')->on('payment_accounts');
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->jsonb('data');
            $table->timestampsTz();
            $table->unique(['shop_id', 'payment_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_account_shop');
    }
};
