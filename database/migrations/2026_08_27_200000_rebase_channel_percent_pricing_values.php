<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE customer_sales_channels
            SET settings = jsonb_set(settings, '{pricing,value}', to_jsonb((settings->'pricing'->>'value')::numeric + 100))
            WHERE settings->'pricing'->>'type' = 'percent'
              AND settings->'pricing'->>'value' ~ '^[+-]?([0-9]+\.?[0-9]*|\.[0-9]+)$'
              AND (settings->'pricing'->>'value')::numeric <> 0
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            UPDATE customer_sales_channels
            SET settings = jsonb_set(settings, '{pricing,value}', to_jsonb((settings->'pricing'->>'value')::numeric - 100))
            WHERE settings->'pricing'->>'type' = 'percent'
              AND settings->'pricing'->>'value' ~ '^[+-]?([0-9]+\.?[0-9]*|\.[0-9]+)$'
              AND (settings->'pricing'->>'value')::numeric <> 0
        SQL);
    }
};
