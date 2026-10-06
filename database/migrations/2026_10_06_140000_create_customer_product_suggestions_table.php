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
        Schema::create('customer_product_suggestions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedInteger('product_id')->index();
            $table->foreign('product_id')->references('id')->on('products');
            $table->string('arm')->comment('ai or bought_together, the A/B half the customer is in');
            $table->unsignedSmallInteger('position');
            $table->string('reason')->nullable();
            $table->string('model')->nullable();
            $table->timestampTz('generated_at');
            $table->index(['customer_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_product_suggestions');
    }
};
