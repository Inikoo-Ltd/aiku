<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 05 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    private array $tables = [
        'org_stock_time_series_records',
        'org_stock_family_time_series_records',
    ];

    private array $columns = [
        'cogs_org_currency',
        'cogs_grp_currency',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            foreach ($this->columns as $column) {
                DB::statement("ALTER TABLE $table ADD COLUMN $column numeric(16,2) DEFAULT 0");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            foreach ($this->columns as $column) {
                DB::statement("ALTER TABLE $table DROP COLUMN $column");
            }
        }
    }
};
