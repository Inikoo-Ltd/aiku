<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('intrastat_export_time_series', function (Blueprint $table) {
            $table->dropUnique('intrastat_export_ts_unique');
            $table->string('partner_tax_number')->nullable()->index()->after('tax_category_id')->comment('Counterparty VAT retained for Intrastat, null means unknown (QV)');
            $table->unique(
                ['organisation_id', 'tariff_code', 'country_id', 'tax_category_id', 'partner_tax_number', 'frequency', 'from', 'to'],
                'intrastat_export_ts_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('intrastat_export_time_series', function (Blueprint $table) {
            $table->dropUnique('intrastat_export_ts_unique');
            $table->dropColumn('partner_tax_number');
            $table->unique(
                ['organisation_id', 'tariff_code', 'country_id', 'tax_category_id', 'frequency', 'from', 'to'],
                'intrastat_export_ts_unique'
            );
        });
    }
};
