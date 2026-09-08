<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->unsignedInteger('minimum_carton_order')->nullable()->comment('MOQ in cartons')->after('units_per_carton');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropColumn('minimum_carton_order');
        });
    }
};
