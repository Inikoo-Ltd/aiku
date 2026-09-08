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
    /**
     * Attribution splits one customer's credit across every channel that touched them, so a channel's
     * customer count is a sum of fractional shares, not a row count: a customer acquired half by
     * google-ads and half by a newsletter contributes 0.5 to each. Integer columns forced each channel
     * to claim the whole customer, which made the channels sum to more than reality.
     */
    public function up(): void
    {
        Schema::table('traffic_source_stats', function (Blueprint $table) {
            $table->decimal('number_customers', 12, 2)->default(0)->change();
            $table->decimal('number_customer_purchases', 12, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('traffic_source_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_customers')->default(0)->change();
            $table->unsignedInteger('number_customer_purchases')->default(0)->change();
        });
    }
};
