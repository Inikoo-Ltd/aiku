<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Jun 2025 18:42:09 Malaysia Time, Sanur, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('exclusive_for_customer_id')->nullable()->index();
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_exclusive_products')->default(0)->index();
        });

    }


    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('exclusive_for_customer_id');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('number_exclusive_products');
        });
    }
};
