<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Dec 2025 13:22:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('not_for_sale_from_master')->default(false);
            $table->boolean('not_for_sale_from_trade_unit')->default(false);
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->boolean('not_for_sale_from_trade_unit')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['not_for_sale_from_master', 'not_for_sale_from_trade_unit']);
        });

        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn(['not_for_sale_from_trade_unit']);
        });
    }
};
