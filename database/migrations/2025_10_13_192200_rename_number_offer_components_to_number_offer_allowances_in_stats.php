<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Oct 2025 19:22:59 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'order_stats',
        'invoice_stats',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if ($this->columnExists($table, 'number_offer_components') && ! $this->columnExists($table, 'number_offer_allowances')) {
                DB::statement("ALTER TABLE \"$table\" RENAME COLUMN \"number_offer_components\" TO \"number_offer_allowances\"");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if ($this->columnExists($table, 'number_offer_allowances') && ! $this->columnExists($table, 'number_offer_components')) {
                DB::statement("ALTER TABLE \"$table\" RENAME COLUMN \"number_offer_allowances\" TO \"number_offer_components\"");
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT 1 AS exists
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
            SQL,
            [$table, $column]
        );

        return (bool) $row;
    }
};
