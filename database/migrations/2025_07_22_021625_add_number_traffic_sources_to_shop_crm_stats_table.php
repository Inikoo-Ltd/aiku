<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2025 13:56:30 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shop_crm_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });
        Schema::table('organisation_crm_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });
        Schema::table('group_crm_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('shop_crm_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });
        Schema::table('organisation_crm_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });
        Schema::table('group_crm_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });
    }
};
