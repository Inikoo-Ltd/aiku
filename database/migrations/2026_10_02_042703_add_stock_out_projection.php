<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organisation_procurement_stats', function (Blueprint $table) {
            $table->jsonb('stock_out_projection')->nullable();
            $table->timestampTz('stock_out_projection_hydrated_at')->nullable();
        });

        Schema::table('org_stock_stats', function (Blueprint $table) {
            $table->decimal('projected_lost_revenue', 16, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organisation_procurement_stats', function (Blueprint $table) {
            $table->dropColumn(['stock_out_projection', 'stock_out_projection_hydrated_at']);
        });

        Schema::table('org_stock_stats', function (Blueprint $table) {
            $table->dropColumn('projected_lost_revenue');
        });
    }
};
