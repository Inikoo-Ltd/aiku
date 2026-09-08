<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->string('cost_status')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('org_stock_movements', function (Blueprint $table) {
            $table->dropColumn('cost_status');
        });
    }
};
