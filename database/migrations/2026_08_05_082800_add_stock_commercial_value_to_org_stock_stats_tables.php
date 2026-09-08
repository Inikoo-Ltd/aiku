<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 05 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $tables = [
        'org_stock_stats',
        'org_stock_family_stats',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('stock_commercial_value', 16)->default(0)->after('stock_value');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('stock_commercial_value');
            });
        }
    }
};
