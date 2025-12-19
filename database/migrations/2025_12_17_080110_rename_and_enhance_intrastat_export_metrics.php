<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::rename('intrastat_metrics', 'intrastat_export_metrics');

        Schema::table('intrastat_export_metrics', function (Blueprint $table) {
            $table->string('delivery_note_type', 20)->nullable()->after('tax_category_id')
                ->comment('order or replacement');

            $table->unsignedBigInteger('invoices_count')->default(0)->after('products_count')
                ->comment('Number of invoices (0 = replacements/samples)');

            $table->json('partner_tax_numbers')->nullable()->after('invoices_count')
                ->comment('Array of unique customer tax numbers with validation status');

            $table->unsignedInteger('valid_tax_numbers_count')->default(0)->after('partner_tax_numbers');
            $table->unsignedInteger('invalid_tax_numbers_count')->default(0)->after('valid_tax_numbers_count');

            $table->string('mode_of_transport', 10)->nullable()->after('invalid_tax_numbers_count')
                ->comment('1=SEA, 2=RAIL, 3=ROAD, 4=AIR, 5=POST, 7=PIPELINE, 8=INLAND_WATERWAY, 9=SELF_PROPULSION');

            $table->string('delivery_terms', 10)->nullable()->after('mode_of_transport')
                ->comment('EXW, FOB, CIF, DAP, DDP, etc.');

            $table->string('nature_of_transaction', 10)->nullable()->after('delivery_terms')
                ->comment('11=Outright purchase/sale, 21=Return/replacement, etc.');

            $table->index('delivery_note_type', 'intrastat_export_metrics_delivery_note_type_idx');
            $table->index('invoices_count', 'intrastat_export_metrics_invoices_count_idx');
        });
    }

    public function down(): void
    {
        Schema::table('intrastat_export_metrics', function (Blueprint $table) {
            $table->dropIndex('intrastat_export_metrics_delivery_note_type_idx');
            $table->dropIndex('intrastat_export_metrics_invoices_count_idx');

            $table->dropColumn([
                'delivery_note_type',
                'invoices_count',
                'partner_tax_numbers',
                'valid_tax_numbers_count',
                'invalid_tax_numbers_count',
                'mode_of_transport',
                'delivery_terms',
                'nature_of_transaction',
            ]);
        });

        Schema::rename('intrastat_export_metrics', 'intrastat_metrics');
    }
};
