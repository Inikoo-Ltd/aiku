<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:24:39 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('intrastat_metrics', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');

            // Daily tracking
            $table->date('date')->index();

            // Intrastat dimensions
            $table->string('tariff_code')->index()->comment('Tariff code (HS code), may contain spaces');
            $table->unsignedSmallInteger('country_id')->index();
            $table->foreign('country_id')->references('id')->on('countries')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('tax_category_id')->nullable()->index();
            $table->foreign('tax_category_id')->references('id')->on('tax_categories')->onUpdate('cascade')->onDelete('cascade');

            // Metrics
            $table->decimal('quantity', 16)->default(0.00);
            $table->decimal('value_org_currency', 16)->default(0.00);
            $table->unsignedBigInteger('weight')->default(0)->comment('Weight in grams');
            $table->unsignedBigInteger('delivery_notes_count')->default(0);
            $table->unsignedBigInteger('products_count')->default(0);

            // Metadata
            $table->jsonb('data')->nullable()->comment('Warnings, metadata');

            $table->timestampsTz();

            // Unique constraint - one record per org + date + tariff + country + tax_category
            $table->unique(
                ['organisation_id', 'date', 'tariff_code', 'country_id', 'tax_category_id'],
                'intrastat_metrics_unique'
            );
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('intrastat_metrics');
    }
};
