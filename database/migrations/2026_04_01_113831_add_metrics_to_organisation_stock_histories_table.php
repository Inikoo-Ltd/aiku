<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 19:41:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->float('percentage_out_of_stock')->default(0);
            $table->decimal('value_dormant_stock_1y', 16)->default(0);
            $table->unsignedSmallInteger('number_org_stocks_not_sold_1y')->default(0);
            $table->float('percentage_value_dormant_stock_1y')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->dropColumn([
                'percentage_out_of_stock',
                'value_dormant_stock_1y',
                'number_org_stocks_not_sold_1y',
                'percentage_value_dormant_stock_1y',
            ]);
        });
    }
};
