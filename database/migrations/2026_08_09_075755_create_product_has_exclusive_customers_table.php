<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * products.exclusive_for_customer_id stays as the "this product is exclusive" marker that
     * 40+ queries already rely on, and holds the primary customer. This pivot carries the full
     * list, because a private label product can be sold to a handful of customers, not just one.
     */
    public function up(): void
    {
        Schema::create('product_has_exclusive_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id')->index();
            $table->foreign('product_id')->references('id')->on('products');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->timestampsTz();
            $table->unique(['product_id', 'customer_id']);
        });

        DB::statement("
            insert into product_has_exclusive_customers (product_id, customer_id, created_at, updated_at)
            select id, exclusive_for_customer_id, now(), now()
            from products
            where exclusive_for_customer_id is not null and deleted_at is null
            on conflict do nothing
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('product_has_exclusive_customers');
    }
};
