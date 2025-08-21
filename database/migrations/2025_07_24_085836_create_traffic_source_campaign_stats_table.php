<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Aug 2025 17:26:53 Central European Summer Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\CRM\TrafficSourceCampaign\TrafficSourceCampaignTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('traffic_source_campaign_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('traffic_source_campaign_id');
            $table->foreign('traffic_source_campaign_id')->references('id')->on('traffic_source_campaigns')->onDelete('cascade');
            foreach (TrafficSourceCampaignTypeEnum::cases() as $type) {
                $column = 'number_campaigns_type_' . Str::snake(str_replace('-', '_', $type->value));
                $table->unsignedInteger($column)->default(0);
            }

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('traffic_source_campaign_stats');
    }
};
