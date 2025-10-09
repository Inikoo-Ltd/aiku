<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Jul 2025 17:36:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('product_has_org_stocks', function (Blueprint $table) {
            $table->unsignedSmallInteger('dividend')->default(1)->comment('helper for non integer quantities');
            $table->unsignedSmallInteger('divisor')->default(1)->comment('helper for non integer quantities');
            $table->unsignedInteger('trade_units_per_org_stock')->nullable()->comment('null if non integer or if org_stock has multiple trade units');

        });
    }


    public function down(): void
    {
        Schema::table('product_has_org_stocks', function (Blueprint $table) {
            $table->dropColumn('divisor', 'dividend', 'trade_units_per_org_stock');
        });
    }
};
