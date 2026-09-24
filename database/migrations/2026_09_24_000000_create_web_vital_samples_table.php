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
        Schema::create('web_vital_samples', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('website_id');
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->unsignedInteger('webpage_id')->nullable()->comment('The page the visitor landed on; null when it is not a webpage, e.g. search results');
            $table->foreign('webpage_id')->references('id')->on('webpages')->nullOnDelete();
            $table->string('device', 8)->comment('desktop or phone, by screen width');
            $table->unsignedInteger('lcp')->nullable()->comment('Largest Contentful Paint in ms; good <= 2500, poor > 4000');
            $table->unsignedInteger('inp')->nullable()->comment('Interaction to Next Paint in ms, null when the visitor never interacted; good <= 200, poor > 500');
            $table->decimal('cls', 6, 3)->nullable()->comment('Cumulative Layout Shift; good <= 0.1, poor > 0.25');
            $table->unsignedInteger('fcp')->nullable()->comment('First Contentful Paint in ms; good <= 1800, poor > 3000');
            $table->unsignedInteger('ttfb')->nullable()->comment('Time to First Byte in ms; good <= 800, poor > 1800');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['webpage_id', 'created_at']);
            $table->index(['website_id', 'created_at']);
        });

        DB::statement("COMMENT ON TABLE web_vital_samples IS 'Real user speed measured in our own visitors browsers (web-vitals), one row per full page load of the storefront. Report the 75th percentile, e.g. percentile_cont(0.75) WITHIN GROUP (ORDER BY lcp), as Google does'");
    }

    public function down(): void
    {
        Schema::dropIfExists('web_vital_samples');
    }
};
