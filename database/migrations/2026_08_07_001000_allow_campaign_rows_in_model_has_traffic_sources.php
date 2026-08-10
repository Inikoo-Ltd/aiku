<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * One pivot row per (model, source) forced multiple campaigns of the same source to collapse
     * into a single row with a null campaign, which made the customers who click more than one
     * mailshot - the most engaged ones - invisible to every per-campaign report. The unique key now
     * includes the campaign, expressed through COALESCE so two campaign-less rows still collide.
     *
     * traffic_source_costs gets the same treatment: updateOrCreate alone cannot stop a concurrent
     * import duplicating a day's spend, and Postgres treats NULL campaigns as distinct in a plain
     * unique constraint.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE model_has_traffic_sources DROP CONSTRAINT IF EXISTS model_has_traffic_sources_model_type_model_id_traffic_source_id');
        DB::statement('DROP INDEX IF EXISTS model_has_traffic_sources_model_type_model_id_traffic_source_id');
        DB::statement('
            CREATE UNIQUE INDEX model_has_traffic_sources_model_source_campaign_unique
            ON model_has_traffic_sources (model_type, model_id, traffic_source_id, COALESCE(traffic_source_campaign_id, 0))
        ');

        DB::statement('
            CREATE UNIQUE INDEX traffic_source_costs_source_campaign_date_unique
            ON traffic_source_costs (traffic_source_id, COALESCE(traffic_source_campaign_id, 0), date)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS traffic_source_costs_source_campaign_date_unique');
        DB::statement('DROP INDEX IF EXISTS model_has_traffic_sources_model_source_campaign_unique');
        DB::statement('
            CREATE UNIQUE INDEX model_has_traffic_sources_model_type_model_id_traffic_source_id
            ON model_has_traffic_sources (model_type, model_id, traffic_source_id)
        ');
    }
};
