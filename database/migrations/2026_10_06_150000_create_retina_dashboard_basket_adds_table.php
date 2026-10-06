<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('retina_dashboard_basket_adds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->string('section')->index()->comment('dashboard section the customer added from, e.g. order_again, suggestions_ai_suggestions');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('order_id')->index()->comment('the basket the product went to');
            $table->decimal('quantity', 16, 3)->nullable()->comment('basket quantity of the product after the add');
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retina_dashboard_basket_adds');
    }
};
