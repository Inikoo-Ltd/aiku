<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->text('traffic_sources')->nullable()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('traffic_sources');
        });
    }
};
