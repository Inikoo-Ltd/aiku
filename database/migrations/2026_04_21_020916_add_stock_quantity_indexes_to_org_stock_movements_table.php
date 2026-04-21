<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Apr 2026 10:11:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->index(['org_stock_id', 'location_id', 'class', 'date'], 'org_stock_movements_stock_location_class_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->dropIndex('org_stock_movements_stock_location_class_date_idx');
        });
    }
};
