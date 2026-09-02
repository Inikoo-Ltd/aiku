<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const COLUMNS = ['estimated_lead_time_days', 'measured_lead_time_days', 'lead_time_samples'];

    public function up(): void
    {
        foreach (['supplier_products', 'org_stocks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'estimated_lead_time_days')) {
                    $table->unsignedSmallInteger('estimated_lead_time_days')->nullable();
                }
                if (!Schema::hasColumn($tableName, 'measured_lead_time_days')) {
                    $table->unsignedSmallInteger('measured_lead_time_days')->nullable();
                }
                if (!Schema::hasColumn($tableName, 'lead_time_samples')) {
                    $table->unsignedInteger('lead_time_samples')->default(0);
                }
            });
        }

        DB::statement("UPDATE supplier_products SET estimated_lead_time_days = (data->>'delivery_time')::int WHERE data->>'delivery_time' ~ '^[0-9]+$' AND estimated_lead_time_days IS NULL");
    }

    public function down(): void
    {
        foreach (['supplier_products', 'org_stocks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (self::COLUMNS as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
