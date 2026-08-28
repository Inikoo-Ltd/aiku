<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('traffic_source_stats', function (Blueprint $table) {
            $table->decimal('total_cost', 16, 2)->default(0)->after('total_customer_revenue');
            $table->decimal('org_total_cost', 16, 2)->default(0)->after('total_cost');

            /* The organisation-scoped listing labels its figures with the organisation's currency, but
               total_customer_revenue is summed from customer_stats.sales_all and is therefore in the
               shop's. Carrying the organisation-currency total as well keeps cost and revenue in the
               same unit on that screen, which is the whole point of showing a return on ad spend. */
            $table->decimal('org_total_customer_revenue', 16, 2)->default(0)->after('total_customer_revenue');
        });
    }

    public function down(): void
    {
        Schema::table('traffic_source_stats', function (Blueprint $table) {
            $table->dropColumn(['total_cost', 'org_total_cost', 'org_total_customer_revenue']);
        });
    }
};
