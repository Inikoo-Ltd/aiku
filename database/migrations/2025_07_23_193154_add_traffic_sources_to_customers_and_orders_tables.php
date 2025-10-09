<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 20:33:05 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('traffic_sources')->nullable()->after('traffic_source_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->text('traffic_sources')->nullable()->after('customer_id');
        });
    }


    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('traffic_sources');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('traffic_sources');
        });
    }
};
