<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shop_sales_targets', function (Blueprint $table) {
            $table->unsignedSmallInteger('invoice_category_id')->nullable();
            $table->foreign('invoice_category_id')->references('id')->on('invoice_categories')->cascadeOnDelete();
            $table->dropUnique(['shop_id', 'month']);
            $table->unique(['shop_id', 'month', 'invoice_category_id'])->nullsNotDistinct();
        });
    }

    public function down(): void
    {
        DB::table('shop_sales_targets')->whereNotNull('invoice_category_id')->delete();

        Schema::table('shop_sales_targets', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'month', 'invoice_category_id']);
            $table->dropForeign(['invoice_category_id']);
            $table->dropColumn('invoice_category_id');
            $table->unique(['shop_id', 'month']);
        });
    }
};
