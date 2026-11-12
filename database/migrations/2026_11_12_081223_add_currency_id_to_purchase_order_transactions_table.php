<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 16:12:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A line keeps the currency its amount is in (INI-247). Existing lines fetched from Aurora are in their order's
     * currency; a line made in aiku was priced from its supplier product, so it takes that product's currency.
     */
    public function up(): void
    {
        Schema::table('purchase_order_transactions', function (Blueprint $table) {
            $table->unsignedSmallInteger('currency_id')->nullable();
        });

        DB::statement(
            'update purchase_order_transactions
            set currency_id = coalesce(
                (
                    select supplier_products.currency_id from supplier_products
                    where supplier_products.id = purchase_order_transactions.supplier_product_id
                        and purchase_order_transactions.source_id is null
                ),
                purchase_orders.currency_id
            )
            from purchase_orders
            where purchase_orders.id = purchase_order_transactions.purchase_order_id'
        );

        Schema::table('purchase_order_transactions', function (Blueprint $table) {
            $table->foreign('currency_id')->references('id')->on('currencies');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_transactions', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn('currency_id');
        });
    }
};
