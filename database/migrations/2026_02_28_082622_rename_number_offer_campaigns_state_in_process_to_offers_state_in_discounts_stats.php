<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    private array $tables = [
        'group_discounts_stats',
        'organisation_discounts_stats',
        'shop_discounts_stats',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $states = ['in_process', 'active', 'finished', 'suspended'];
            foreach ($states as $state) {
                $oldCol = "number_offer_campaigns_state_$state";
                $newCol = "number_offer_campaigns_offers_state_$state";
                if ($this->columnExists($table, $oldCol) && !$this->columnExists($table, $newCol)) {
                    DB::statement("ALTER TABLE \"$table\" RENAME COLUMN \"$oldCol\" TO \"$newCol\"");
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            $states = ['in_process', 'active', 'finished', 'suspended'];
            foreach ($states as $state) {
                $oldCol = "number_offer_campaigns_offers_state_$state";
                $newCol = "number_offer_campaigns_state_$state";
                if ($this->columnExists($table, $oldCol) && !$this->columnExists($table, $newCol)) {
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

        return (bool)$row;
    }
};
