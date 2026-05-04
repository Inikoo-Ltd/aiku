<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE brand_time_series_records ADD COLUMN organisation_id smallint');
        DB::statement('ALTER TABLE brand_time_series_records ADD COLUMN shop_id smallint');

        DB::statement('DELETE FROM brand_time_series_records');

        DB::statement('CREATE INDEX brand_time_series_records_brand_org_index ON brand_time_series_records (brand_time_series_id, organisation_id)');
        DB::statement('CREATE INDEX brand_time_series_records_brand_shop_index ON brand_time_series_records (brand_time_series_id, shop_id)');
        DB::statement('CREATE UNIQUE INDEX brand_time_series_records_unique ON brand_time_series_records (brand_time_series_id, shop_id, frequency, period)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS brand_time_series_records_unique');
        DB::statement('DROP INDEX IF EXISTS brand_time_series_records_brand_shop_index');
        DB::statement('DROP INDEX IF EXISTS brand_time_series_records_brand_org_index');
        DB::statement('ALTER TABLE brand_time_series_records DROP COLUMN IF EXISTS shop_id');
        DB::statement('ALTER TABLE brand_time_series_records DROP COLUMN IF EXISTS organisation_id');
    }
};
