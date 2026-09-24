<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('crux_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->unsignedInteger('webpage_id')->nullable()->index()->comment('Null when the row is the whole website (origin), not one page');
            $table->foreign('webpage_id')->references('id')->on('webpages')->cascadeOnDelete();
            $table->string('url')->comment('The page URL or website origin Google measured');
            $table->string('form_factor')->comment('all, desktop or phone');
            $table->date('period_start')->comment('First day of the 28 day window of real Chrome visits');
            $table->date('period_end')->comment('Last day of the 28 day window, one row per week');
            $table->unsignedInteger('lcp_p75')->nullable()->comment('Largest Contentful Paint, 75th percentile in ms; good <= 2500, poor > 4000');
            $table->unsignedInteger('inp_p75')->nullable()->comment('Interaction to Next Paint, 75th percentile in ms; good <= 200, poor > 500');
            $table->decimal('cls_p75', 6, 3)->nullable()->comment('Cumulative Layout Shift, 75th percentile; good <= 0.1, poor > 0.25');
            $table->unsignedInteger('fcp_p75')->nullable()->comment('First Contentful Paint, 75th percentile in ms; good <= 1800, poor > 3000');
            $table->unsignedInteger('ttfb_p75')->nullable()->comment('Time to First Byte, 75th percentile in ms; good <= 800, poor > 1800');
            $table->jsonb('histograms')->default('{}')->comment('Share of visits per metric as [good, needs improvement, poor], e.g. {"lcp": [0.9, 0.07, 0.03]}');
            $table->timestampsTz();
            $table->unique(['website_id', 'webpage_id', 'form_factor', 'period_end'])->nullsNotDistinct();
        });

        DB::statement("COMMENT ON TABLE crux_records IS 'Real user speed from the Chrome UX Report (CrUX), fetched weekly: p75 of the Core Web Vitals per page or whole website (webpage_id null), per device'");
    }

    public function down(): void
    {
        Schema::dropIfExists('crux_records');
    }
};
