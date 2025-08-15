<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Aug 2025 17:26:15 Central European Summer Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['traffic_source_id']);
            $table->dropColumn('traffic_source_id');
        });

        Schema::table('group_crm_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });

        Schema::table('shop_crm_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });

        Schema::table('organisation_crm_stats', function (Blueprint $table) {
            $table->dropColumn('number_traffic_sources');
        });
    }


    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('traffic_source_id')->nullable()->after('id');
            $table->foreign('traffic_source_id')->references('id')->on('traffic_sources')->nullOnDelete();
        });

        Schema::table('group_crm_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });

        Schema::table('shop_crm_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });

        Schema::table('organisation_crm_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_traffic_sources')->default(0);
        });
    }
};
