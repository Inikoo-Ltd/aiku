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
        // Add is_single_trade_unit to products table
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_single_trade_unit')->default(false)->index()->after('trade_config')
                ->comment('Indicates if the product has a single trade unit');
        });

        // Add is_single_trade_unit to master_assets table
        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('is_single_trade_unit')->default(false)->index()->after('status')
                ->comment('Indicates if the master asset has a single trade unit');
        });

        // Add is_single_trade_unit to org_stocks table
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->boolean('is_single_trade_unit')->default(false)->index()->after('is_raw_material_in_organisation')
                ->comment('Indicates if the org stock has a single trade unit');
        });

        // Add is_single_trade_unit to stocks table
        Schema::table('stocks', function (Blueprint $table) {
            $table->boolean('is_single_trade_unit')->default(false)->index()->after('raw_material')
                ->comment('Indicates if the stock has a single trade unit');
        });
    }

    public function down(): void
    {
        // Remove is_single_trade_unit from products table
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_single_trade_unit');
        });

        // Remove is_single_trade_unit from master_assets table
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn('is_single_trade_unit');
        });

        // Remove is_single_trade_unit from org_stocks table
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropColumn('is_single_trade_unit');
        });

        // Remove is_single_trade_unit from stocks table
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('is_single_trade_unit');
        });
    }
};
