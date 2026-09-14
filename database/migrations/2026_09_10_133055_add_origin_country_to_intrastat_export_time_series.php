<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('intrastat_export_time_series', function (Blueprint $table) {
            $table->dropUnique('intrastat_export_ts_unique');
            $table->unsignedSmallInteger('origin_country_id')->nullable()->index()->after('country_id');
            $table->foreign('origin_country_id')->references('id')->on('countries')->onUpdate('cascade')->onDelete('cascade');
            $table->unique(
                ['organisation_id', 'tariff_code', 'country_id', 'origin_country_id', 'tax_category_id', 'partner_tax_number', 'frequency', 'from', 'to'],
                'intrastat_export_ts_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('intrastat_export_time_series', function (Blueprint $table) {
            $table->dropUnique('intrastat_export_ts_unique');
            $table->dropForeign(['origin_country_id']);
            $table->dropColumn('origin_country_id');
            $table->unique(
                ['organisation_id', 'tariff_code', 'country_id', 'tax_category_id', 'partner_tax_number', 'frequency', 'from', 'to'],
                'intrastat_export_ts_unique'
            );
        });
    }
};
