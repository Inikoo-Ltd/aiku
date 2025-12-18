<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('intrastat_import_metrics', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');

            $table->date('date')->index();

            $table->string('tariff_code')->index()->comment('Part tariff code');
            $table->unsignedSmallInteger('country_id')->index()->comment('Supplier country');
            $table->foreign('country_id')->references('id')->on('countries')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('tax_category_id')->nullable()->index();
            $table->foreign('tax_category_id')->references('id')->on('tax_categories')->onUpdate('cascade')->onDelete('cascade');

            $table->decimal('quantity', 16)->default(0.00);
            $table->decimal('value_org_currency', 16)->default(0.00);
            $table->unsignedBigInteger('weight')->default(0)->comment('Weight in grams');
            $table->unsignedBigInteger('supplier_deliveries_count')->default(0);
            $table->unsignedBigInteger('parts_count')->default(0);
            $table->unsignedBigInteger('invoices_count')->default(0);

            $table->json('supplier_tax_numbers')->nullable()->comment('Array of unique supplier tax numbers with validation status');
            $table->unsignedInteger('valid_tax_numbers_count')->default(0);
            $table->unsignedInteger('invalid_tax_numbers_count')->default(0);

            $table->string('mode_of_transport', 10)->nullable()->comment('1=SEA, 2=RAIL, 3=ROAD, 4=AIR, 5=POST, 7=PIPELINE, 8=INLAND_WATERWAY, 9=SELF_PROPULSION');
            $table->string('delivery_terms', 10)->nullable()->comment('EXW, FOB, CIF, DAP, DDP, etc.');
            $table->string('nature_of_transaction', 10)->nullable()->comment('11=Outright purchase/sale, 21=Return/replacement, etc.');

            $table->jsonb('data')->nullable()->comment('Warnings, metadata');

            $table->timestampsTz();

            $table->unique(
                ['organisation_id', 'date', 'tariff_code', 'country_id', 'tax_category_id'],
                'intrastat_import_metrics_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intrastat_import_metrics');
    }
};
