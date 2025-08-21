<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Aug 2025 17:27:50 Central European Summer Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_comms', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });
        Schema::table('shop_comms_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });
        Schema::table('organisation_comms_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });
        Schema::table('group_comms_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('customer_comms', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });
        Schema::table('shop_comms_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });
        Schema::table('organisation_comms_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });
        Schema::table('group_comms_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });
    }
};
