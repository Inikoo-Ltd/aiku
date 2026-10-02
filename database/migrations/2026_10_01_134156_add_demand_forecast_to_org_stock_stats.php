<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stock_stats', function (Blueprint $table) {
            $table->jsonb('demand_forecast')->nullable();
            $table->timestampTz('demand_forecast_hydrated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('org_stock_stats', function (Blueprint $table) {
            $table->dropColumn(['demand_forecast', 'demand_forecast_hydrated_at']);
        });
    }
};
