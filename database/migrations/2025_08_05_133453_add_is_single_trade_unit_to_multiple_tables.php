<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 Aug 2025 13:34:53 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_single_trade_unit')->default(false)->index()->after('trade_config')
                ->comment('Indicates if the product has a single trade unit');
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('is_single_trade_unit')->default(false)->index()->after('status')
                ->comment('Indicates if the master asset has a single trade unit');
        });

        Schema::table('org_stocks', function (Blueprint $table) {
            $table->boolean('is_single_trade_unit')->default(false)->index()->after('is_raw_material_in_organisation')
                ->comment('Indicates if the org stock has a single trade unit');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->boolean('is_single_trade_unit')->default(false)->index()->after('raw_material')
                ->comment('Indicates if the stock has a single trade unit');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_single_trade_unit');
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn('is_single_trade_unit');
        });

        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropColumn('is_single_trade_unit');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('is_single_trade_unit');
        });
    }
};
