<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $columns = [
            'discount_amount_external'       => 'numeric(16,2) DEFAULT 0',
            'discount_org_currency_external' => 'numeric(16,2) DEFAULT 0',
            'discount_grp_currency_external' => 'numeric(16,2) DEFAULT 0',
        ];

        foreach ($columns as $column => $definition) {
            DB::statement("
                DO \$\$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM information_schema.columns
                        WHERE table_schema = 'public'
                            AND table_name = 'offer_campaign_time_series_records'
                            AND column_name = '{$column}'
                    ) THEN
                        EXECUTE 'ALTER TABLE \"offer_campaign_time_series_records\" ADD COLUMN \"{$column}\" {$definition}';
                    END IF;
                END \$\$;
            ");
        }
    }

    public function down(): void
    {
        $columns = [
            'discount_amount_external',
            'discount_org_currency_external',
            'discount_grp_currency_external',
        ];

        foreach ($columns as $column) {
            DB::statement("
                DO \$\$
                BEGIN
                    IF EXISTS (
                        SELECT 1
                        FROM information_schema.columns
                        WHERE table_schema = 'public'
                            AND table_name = 'offer_campaign_time_series_records'
                            AND column_name = '{$column}'
                    ) THEN
                        EXECUTE 'ALTER TABLE \"offer_campaign_time_series_records\" DROP COLUMN \"{$column}\"';
                    END IF;
                END \$\$;
            ");
        }
    }
};
