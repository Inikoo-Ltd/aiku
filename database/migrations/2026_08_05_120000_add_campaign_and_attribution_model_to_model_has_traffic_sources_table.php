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
        Schema::table('model_has_traffic_sources', function (Blueprint $table) {
            $table->unsignedInteger('traffic_source_campaign_id')->nullable()->after('traffic_source_id');
            $table->foreign('traffic_source_campaign_id')->references('id')->on('traffic_source_campaigns')->nullOnDelete();
            $table->string('attribution_model')->nullable()->after('share');
            $table->index('traffic_source_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('model_has_traffic_sources', function (Blueprint $table) {
            $table->dropForeign(['traffic_source_campaign_id']);
            $table->dropColumn(['traffic_source_campaign_id', 'attribution_model']);
        });
    }
};
