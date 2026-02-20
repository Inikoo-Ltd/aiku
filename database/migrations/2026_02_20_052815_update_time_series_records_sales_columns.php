<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $definitions = [
            'asset_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'master_asset_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'collection_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'master_collection_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'offer_campaign_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'offer_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'product_category_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0.00',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0.00',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0.00',
            ],
            'master_product_category_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0.00',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0.00',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0.00',
            ],
            'platform_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'sales_channel_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'invoice_category_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'shop_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'master_shop_time_series_records' => [
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'organisation_time_series_records' => [
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
        ];

        foreach ($definitions as $table => $columns) {
            foreach ($columns as $column => $definition) {
                $this->renameAndAddInternal($table, $column, $definition);
            }
        }
    }

    public function down(): void
    {
        $definitions = [
            'asset_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'master_asset_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'collection_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'master_collection_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'offer_campaign_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'offer_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'product_category_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0.00',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0.00',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0.00',
            ],
            'master_product_category_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0.00',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0.00',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0.00',
            ],
            'platform_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'sales_channel_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'invoice_category_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'shop_time_series_records' => [
                'sales' => 'numeric(16,2) DEFAULT 0',
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'master_shop_time_series_records' => [
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
            'organisation_time_series_records' => [
                'sales_org_currency' => 'numeric(16,2) DEFAULT 0',
                'sales_grp_currency' => 'numeric(16,2) DEFAULT 0',
            ],
        ];

        foreach ($definitions as $table => $columns) {
            foreach ($columns as $column => $definition) {
                $this->renameBackAndDropInternal($table, $column);
            }
        }
    }

    private function renameAndAddInternal(string $table, string $column, string $definition): void
    {
        DB::statement("
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_schema = 'public'
                        AND table_name = '{$table}'
                        AND column_name = '{$column}'
                ) AND NOT EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_schema = 'public'
                        AND table_name = '{$table}'
                        AND column_name = '{$column}_external'
                ) THEN
                    EXECUTE 'ALTER TABLE \"{$table}\" RENAME COLUMN \"{$column}\" TO \"{$column}_external\"';
                END IF;
            END $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_schema = 'public'
                        AND table_name = '{$table}'
                        AND column_name = '{$column}_internal'
                ) THEN
                    EXECUTE 'ALTER TABLE \"{$table}\" ADD COLUMN \"{$column}_internal\" {$definition}';
                END IF;
            END $$;
        ");
    }

    private function renameBackAndDropInternal(string $table, string $column): void
    {
        DB::statement("
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_schema = 'public'
                        AND table_name = '{$table}'
                        AND column_name = '{$column}_external'
                ) AND NOT EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_schema = 'public'
                        AND table_name = '{$table}'
                        AND column_name = '{$column}'
                ) THEN
                    EXECUTE 'ALTER TABLE \"{$table}\" RENAME COLUMN \"{$column}_external\" TO \"{$column}\"';
                END IF;
            END $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_schema = 'public'
                        AND table_name = '{$table}'
                        AND column_name = '{$column}_internal'
                ) THEN
                    EXECUTE 'ALTER TABLE \"{$table}\" DROP COLUMN \"{$column}_internal\"';
                END IF;
            END $$;
        ");
    }
};
