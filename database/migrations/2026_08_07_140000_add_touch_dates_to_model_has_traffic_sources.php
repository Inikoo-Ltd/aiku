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
     * When each touch actually happened, as opposed to when the pivot row was written. Without it a
     * touch can be credited with revenue that predates it: a customer who registered in 2022 and
     * clicked a newsletter yesterday was handing that newsletter four years of prior trade.
     *
     * The dates come from the touch history itself, so they survive recalculation and backfilling.
     */
    public function up(): void
    {
        Schema::table('model_has_traffic_sources', function (Blueprint $table) {
            $table->timestampTz('first_touch_at')->nullable()->after('attribution_model');
            $table->timestampTz('last_touch_at')->nullable()->after('first_touch_at');
            $table->index('last_touch_at');
        });
    }

    public function down(): void
    {
        Schema::table('model_has_traffic_sources', function (Blueprint $table) {
            $table->dropIndex(['last_touch_at']);
            $table->dropColumn(['first_touch_at', 'last_touch_at']);
        });
    }
};
