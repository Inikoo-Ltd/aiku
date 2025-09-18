<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Sept 2025 20:44:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('model_has_trade_units', function (Blueprint $table) {
            $table->decimal('quantity', 21, 8)->default(1)->change();
        });

        Schema::table('product_has_org_stocks', function (Blueprint $table) {
            $table->decimal('quantity', 21, 8)->change();
        });

        Schema::table('master_asset_has_stocks', function (Blueprint $table) {
            $table->decimal('quantity', 21, 8)->change();
        });
    }

    public function down(): void
    {
        Schema::table('model_has_trade_units', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->default(1)->change();
        });
        Schema::table('product_has_org_stocks', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
        });
        Schema::table('master_asset_has_stocks', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
        });
    }
};
