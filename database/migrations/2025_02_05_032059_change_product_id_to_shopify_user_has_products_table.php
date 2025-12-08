<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Feb 2025 14:26:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_user_has_products', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->change();
            $table->unsignedBigInteger('portfolio_id')->change();
            $table->unsignedBigInteger('shopify_product_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shopify_user_has_products', function (Blueprint $table) {});
    }
};
