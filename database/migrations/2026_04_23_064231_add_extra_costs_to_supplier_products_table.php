<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Apr 2026 17:47:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->decimal('extra_costs', 8, 3)->default(0)->comment('Estimated percentage of extra costs');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropColumn('extra_costs');
        });
    }
};
