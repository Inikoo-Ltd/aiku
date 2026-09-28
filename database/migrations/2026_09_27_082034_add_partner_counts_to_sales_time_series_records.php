<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $tables = [
        'shop_time_series_records',
        'invoice_category_time_series_records',
        'platform_time_series_records',
        'brand_time_series_records',
        'organisation_time_series_records',
        'sales_channel_time_series_records',
        'master_shop_time_series_records',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('invoices_internal')->default(0);
                $table->unsignedInteger('refunds_internal')->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['invoices_internal', 'refunds_internal']);
            });
        }
    }
};
