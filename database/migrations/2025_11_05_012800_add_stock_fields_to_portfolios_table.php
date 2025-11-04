<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Nov 2025 01:34:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->boolean('mark_for_update_stock')->default(false);
            $table->dateTimeTz('stock_last_updated_at')->nullable();
            $table->dateTimeTz('stock_last_fail_updated_at')->nullable();
            $table->integer('last_stock_value')->nullable();
        });

        Schema::table('platform_portfolio_logs', function (Blueprint $table) {
            $table->integer('last_stock_value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropColumn('mark_for_update_stock');
            $table->dropColumn('stock_last_updated_at');
            $table->dropColumn('stock_last_fail_updated_at');
            $table->dropColumn('last_stock_value');
        });

        Schema::table('platform_portfolio_logs', function (Blueprint $table) {
            $table->dropColumn('last_stock_value');
        });
    }
};
