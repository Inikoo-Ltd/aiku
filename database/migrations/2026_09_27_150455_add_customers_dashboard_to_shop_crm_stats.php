<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shop_crm_stats', function (Blueprint $table) {
            $table->jsonb('customers_dashboard')->nullable();
            $table->timestampTz('customers_dashboard_hydrated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shop_crm_stats', function (Blueprint $table) {
            $table->dropColumn(['customers_dashboard', 'customers_dashboard_hydrated_at']);
        });
    }
};
