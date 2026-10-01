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
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->jsonb('sales_forecast')->nullable();
            $table->timestampTz('sales_forecast_hydrated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shop_stats', function (Blueprint $table) {
            $table->dropColumn(['sales_forecast', 'sales_forecast_hydrated_at']);
        });
    }
};
