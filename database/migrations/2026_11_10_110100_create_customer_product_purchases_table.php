<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('customer_product_purchases', function (Blueprint $table) {
            $table->unsignedInteger('customer_id');
            $table->unsignedSmallInteger('shop_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('family_id')->nullable();
            $table->integer('orders');
            $table->integer('orders_12m');
            $table->decimal('quantity_12m', 16, 3);
            $table->decimal('spend_12m', 16, 2);
            $table->timestampTz('first_ordered_at');
            $table->timestampTz('last_ordered_at');
            $table->primary(['customer_id', 'product_id']);
            $table->index(['shop_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_product_purchases');
    }
};
