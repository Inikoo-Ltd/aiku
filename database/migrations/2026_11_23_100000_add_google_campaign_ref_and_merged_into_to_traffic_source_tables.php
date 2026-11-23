<?php

/*
 * Created: Mon, 23 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('traffic_source_clicks', function (Blueprint $table) {
            $table->string('google_campaign_ref')->nullable()->after('campaign_ref');
        });

        Schema::table('traffic_source_campaigns', function (Blueprint $table) {
            $table->unsignedInteger('merged_into_id')->nullable()->index();
            $table->foreign('merged_into_id')->references('id')->on('traffic_source_campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('traffic_source_campaigns', function (Blueprint $table) {
            $table->dropForeign(['merged_into_id']);
            $table->dropColumn('merged_into_id');
        });

        Schema::table('traffic_source_clicks', function (Blueprint $table) {
            $table->dropColumn('google_campaign_ref');
        });
    }
};
