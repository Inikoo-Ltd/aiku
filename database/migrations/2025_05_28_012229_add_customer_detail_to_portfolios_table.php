<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 May 2025 10:15:38 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->string('customer_product_name')->nullable();
            $table->string('customer_price')->nullable();
            $table->string('customer_description')->nullable();
            $table->string('shopify_product_id')->nullable();
            $table->jsonb('errors_response')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropColumn([
                'customer_product_name',
                'customer_price',
                'customer_description',
                'shopify_product_id',
                'errors_response'
            ]);
        });
    }
};
