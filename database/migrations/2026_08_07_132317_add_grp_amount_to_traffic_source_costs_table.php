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
     * Group-wide ad spend needs an amount in the group's own currency: totalling a shop's own amounts
     * across GBP, EUR and PLN produces a number that means nothing, which is why the group dashboard
     * had no spend or ROAS at all.
     *
     * No backfill: the table is empty in production, and a correct historic value needs the exchange
     * rate of the day each cost was spent. Should rows ever predate this, re-import them rather than
     * convert at today's rate.
     */
    public function up(): void
    {
        Schema::table('traffic_source_costs', function (Blueprint $table) {
            $table->decimal('grp_amount', 16, 2)->default(0)->after('org_amount');
        });
    }

    public function down(): void
    {
        Schema::table('traffic_source_costs', function (Blueprint $table) {
            $table->dropColumn('grp_amount');
        });
    }
};
