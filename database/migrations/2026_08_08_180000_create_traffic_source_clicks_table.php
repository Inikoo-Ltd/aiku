<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * One row per identified channel arrival, with the fine detail the aggregates deliberately drop:
     * IP, device, landing page, click id. Two consumers: click-fraud forensics on paid channels
     * (many clicks from one IP, the ad platform's click id to dispute with) and per-webpage arrival
     * analytics for ShowWebpage.
     *
     * Written from a queued job, never the storefront hot path. Pruned after 90 days - it holds IPs,
     * so it is kept only as long as the fraud-prevention purpose justifies.
     */
    public function up(): void
    {
        Schema::create('traffic_source_clicks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
            $table->unsignedInteger('website_id')->nullable();
            $table->foreign('website_id')->references('id')->on('websites')->onDelete('cascade');
            $table->unsignedInteger('webpage_id')->nullable();
            $table->foreign('webpage_id')->references('id')->on('webpages')->nullOnDelete();
            $table->string('type', 64)->index();
            $table->string('campaign_ref')->nullable();
            $table->string('click_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('device_type', 32)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->string('user_agent', 1024)->nullable();
            $table->text('url')->nullable();
            $table->boolean('is_repeat')->default(false);
            $table->timestampTz('created_at');

            $table->index(['shop_id', 'created_at']);
            $table->index(['webpage_id', 'created_at']);
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_source_clicks');
    }
};
