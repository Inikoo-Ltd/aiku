<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement("
            insert into product_has_exclusive_customers (product_id, customer_id, created_at, updated_at)
            select id, exclusive_for_customer_id, now(), now()
            from products
            where exclusive_for_customer_id is not null and deleted_at is null
            on conflict do nothing
        ");

        DB::statement("
            update customers
            set number_exclusive_products = counts.number_exclusive_products
            from (
                select customer_id, count(*) as number_exclusive_products
                from product_has_exclusive_customers
                group by customer_id
            ) counts
            where counts.customer_id = customers.id
            and customers.number_exclusive_products is distinct from counts.number_exclusive_products
        ");
    }

    public function down(): void
    {
    }
};
