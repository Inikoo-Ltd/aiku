<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * segment_product_sales is derived data rebuilt nightly: its rows are deleted both ways so rows with the old level names are never read before the next rebuild.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::table('segment_product_sales')->delete();

        Schema::table('segment_product_sales', function (Blueprint $table) {
            $table->string('area', 8)->nullable();
            $table->string('region', 24)->nullable()->change();
            $table->index(['shop_id', 'level', 'country_code', 'area']);
        });
    }

    public function down(): void
    {
        DB::table('segment_product_sales')->delete();

        Schema::table('segment_product_sales', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'level', 'country_code', 'area']);
            $table->dropColumn('area');
            $table->string('region', 2)->nullable()->change();
        });
    }
};
