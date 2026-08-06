<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * TrafficSourceHydrateCustomers aggregates by traffic_source_id + model_type, and the only
     * existing index leads on model_type/model_id, so every hydrator run walked the whole pivot.
     * The hydrator fires on every registration, order cancel and email-click recalculation.
     */
    public function up(): void
    {
        Schema::table('model_has_traffic_sources', function (Blueprint $table) {
            $table->index(['traffic_source_id', 'model_type']);
        });
    }

    public function down(): void
    {
        Schema::table('model_has_traffic_sources', function (Blueprint $table) {
            $table->dropIndex(['traffic_source_id', 'model_type']);
        });
    }
};
