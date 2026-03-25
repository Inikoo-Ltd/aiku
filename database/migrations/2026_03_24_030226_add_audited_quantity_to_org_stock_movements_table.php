<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Mar 2026 11:25:30 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->decimal('audited_quantity', 18, 6)->nullable();
            $table->decimal('running_quantity', 18, 6)->nullable()->comment('running quantity on org_stock/location');
            $table->decimal('running_quantity_org_stock', 18, 6)->nullable()->comment('running quantity on org_stock');
        });
    }


    public function down(): void
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->dropColumn('audited_quantity');
            $table->dropColumn('running_quantity');
            $table->dropColumn('running_quantity_org_stock');
        });
    }
};
