<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('intrastat_export_metrics', function (Blueprint $table) {
            $constraintExists = DB::select("
                SELECT constraint_name 
                FROM information_schema.table_constraints 
                WHERE table_name = 'intrastat_export_metrics' 
                AND constraint_name = 'intrastat_metrics_unique'
                AND constraint_type = 'UNIQUE'
            ");

            if (!empty($constraintExists)) {
                $table->dropUnique('intrastat_metrics_unique');
            }

            $newConstraintExists = DB::select("
                SELECT constraint_name 
                FROM information_schema.table_constraints 
                WHERE table_name = 'intrastat_export_metrics' 
                AND constraint_name = 'intrastat_export_metrics_unique'
                AND constraint_type = 'UNIQUE'
            ");

            if (empty($newConstraintExists)) {
                $table->unique(
                    ['organisation_id', 'date', 'tariff_code', 'country_id', 'tax_category_id', 'delivery_note_type'],
                    'intrastat_export_metrics_unique'
                );
            }
        });
    }

    public function down(): void
    {

    }
};
