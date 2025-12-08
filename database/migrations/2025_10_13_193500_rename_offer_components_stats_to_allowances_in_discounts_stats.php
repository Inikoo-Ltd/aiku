<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Oct 2025 19:29:12 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'group_discounts_stats',
        'organisation_discounts_stats',
        'shop_discounts_stats',
    ];

    private string $componentsPrefix = 'number_offer_components_state_';

    private string $allowancesPrefix = 'number_offer_allowances_state_';

    public function up(): void
    {
        foreach ($this->tables as $table) {
            // Base counters
            if ($this->columnExists($table, 'number_offer_components') && ! $this->columnExists($table, 'number_offer_allowances')) {
                DB::statement("ALTER TABLE \"$table\" RENAME COLUMN \"number_offer_components\" TO \"number_offer_allowances\"");
            }
            if ($this->columnExists($table, 'number_current_offer_components') && ! $this->columnExists($table, 'number_current_offer_allowances')) {
                DB::statement("ALTER TABLE \"$table\" RENAME COLUMN \"number_current_offer_components\" TO \"number_current_offer_allowances\"");
            }

            // Dynamic state counters
            $stateColumns = $this->getColumnsByPrefix($table, $this->componentsPrefix);
            foreach ($stateColumns as $oldCol) {
                $suffix = substr($oldCol, strlen($this->componentsPrefix));
                $newCol = $this->allowancesPrefix.$suffix;
                if (! $this->columnExists($table, $newCol)) {
                    DB::statement("ALTER TABLE \"$table\" RENAME COLUMN \"$oldCol\" TO \"$newCol\"");
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            // Base counters
            if ($this->columnExists($table, 'number_offer_allowances') && ! $this->columnExists($table, 'number_offer_components')) {
                DB::statement("ALTER TABLE \"$table\" RENAME COLUMN \"number_offer_allowances\" TO \"number_offer_components\"");
            }
            if ($this->columnExists($table, 'number_current_offer_allowances') && ! $this->columnExists($table, 'number_current_offer_components')) {
                DB::statement("ALTER TABLE \"$table\" RENAME COLUMN \"number_current_offer_allowances\" TO \"number_current_offer_components\"");
            }

            // Dynamic state counters
            $stateColumns = $this->getColumnsByPrefix($table, $this->allowancesPrefix);
            foreach ($stateColumns as $oldCol) {
                $suffix = substr($oldCol, strlen($this->allowancesPrefix));
                $newCol = $this->componentsPrefix.$suffix;
                if (! $this->columnExists($table, $newCol)) {
                    DB::statement("ALTER TABLE \"$table\" RENAME COLUMN \"$oldCol\" TO \"$newCol\"");
                }
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

    private function getColumnsByPrefix(string $table, string $prefix): array
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = ?
              AND column_name LIKE ?
            ORDER BY column_name
            SQL,
            [$table, $prefix.'%']
        );

        return array_map(fn ($r) => $r->column_name, $rows);
    }
};
