<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Mar 2026 23:05:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('location_org_stock_histories', function (Blueprint $table) {
            $table->decimal('org_stock_value', 16)->default(0);
            $table->decimal('grp_stock_value', 16)->default(0);
        });

        Schema::table('org_stock_histories', function (Blueprint $table) {
            $table->boolean('sold_within_1y')->nullable();
            $table->date('last_sold_date')->nullable();
            $table->float('non_moving_1y')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('location_org_stock_histories', function (Blueprint $table) {
            $table->dropColumn(['org_stock_value', 'grp_stock_value']);
        });

        Schema::table('org_stock_histories', function (Blueprint $table) {
            $table->dropColumn(['sold_within_1y', 'last_sold_date', 'non_moving_1y']);
        });
    }
};
