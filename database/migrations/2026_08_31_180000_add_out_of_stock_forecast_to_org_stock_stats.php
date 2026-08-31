<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stock_stats', function (Blueprint $table) {
            $table->decimal('predicted_daily_usage', 14, 4)->nullable()
                ->comment('momentum-weighted dispatches/day, seasonality adjusted');
            $table->decimal('days_of_cover', 8, 1)->nullable();
            $table->date('predicted_out_of_stock_at')->nullable()->index();
            $table->decimal('days_of_cover_pessimistic', 8, 1)->nullable()
                ->comment('90th percentile demand: stock could run out this early');
            $table->decimal('demand_variability', 8, 4)->nullable()
                ->comment('coefficient of variation of daily demand on in-stock days');
            $table->string('forecast_source', 16)->nullable()
                ->comment('croston|holt|time_series|siblings|family');
            $table->decimal('recommended_order_quantity', 16, 3)->nullable()
                ->comment('units to reorder now: cover lead time + review period + safety, minus available and on order, rounded to supplier pack');
        });
    }

    public function down(): void
    {
        Schema::table('org_stock_stats', function (Blueprint $table) {
            $table->dropColumn([
                'predicted_daily_usage',
                'days_of_cover',
                'predicted_out_of_stock_at',
                'days_of_cover_pessimistic',
                'demand_variability',
                'forecast_source',
                'recommended_order_quantity',
            ]);
        });
    }
};
