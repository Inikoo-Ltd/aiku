<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Sept 2025 15:22:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_shops', function (Blueprint $table) {
            $table->decimal('cost_price_ratio', 16, 3)->default(2);
            $table->decimal('price_rrp_ratio', 16, 3)->default(4);
        });
    }


    public function down(): void
    {
        Schema::table('master_shops', function (Blueprint $table) {
            $table->dropColumn('cost_price_ratio');
            $table->dropColumn('price_rrp_ratio');
        });
    }
};
