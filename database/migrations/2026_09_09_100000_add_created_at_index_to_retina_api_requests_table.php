<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The nightly prune and the hourly inflow monitor both filter on created_at alone, which the
     * (customer_id, created_at) index cannot serve, and the per-customer cap walks ids within a
     * customer.
     */
    public function up(): void
    {
        Schema::table('retina_api_requests', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['customer_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('retina_api_requests', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['customer_id', 'id']);
        });
    }
};
