<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 18:00:00 Central European Summer Time, Bratislava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('platform_sales_channel_time_series_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('platform_time_series_id');
            $table->foreign('platform_time_series_id')->references('id')->on('platform_time_series')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('organisation_id');
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('sales_channel_id');
            $table->foreign('sales_channel_id')->references('id')->on('sales_channels')->cascadeOnDelete()->cascadeOnUpdate();
            $table->char('frequency', 1);
            $table->decimal('sales_external', 16)->default(0);
            $table->decimal('sales_org_currency_external', 16)->default(0);
            $table->decimal('sales_grp_currency_external', 16)->default(0);
            $table->unsignedInteger('invoices')->default(0);
            $table->timestampTz('from')->nullable();
            $table->timestampTz('to')->nullable();
            $table->string('period');
            $table->timestampsTz();

            $table->unique(
                ['platform_time_series_id', 'shop_id', 'sales_channel_id', 'period', 'frequency'],
                'platform_sales_channel_time_series_records_nk_unique'
            );
            $table->index(['platform_time_series_id', 'shop_id', 'from'], 'psctsr_series_shop_from_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_sales_channel_time_series_records');
    }
};
