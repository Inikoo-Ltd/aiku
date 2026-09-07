<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up()
    {
        DB::statement("
            update org_agent_stats
            set number_stock_deliveries = counts.total,
                number_current_stock_deliveries = counts.current_total
            from (
                select
                    stock_deliveries.parent_id as org_agent_id,
                    count(*) as total,
                    count(*) filter (where stock_deliveries.state not in ('cancelled', 'not_received')) as current_total
                from stock_deliveries
                where stock_deliveries.parent_type = 'OrgAgent'
                group by stock_deliveries.parent_id
            ) counts
            where org_agent_stats.org_agent_id = counts.org_agent_id
        ");
    }

    public function down()
    {
        // ponytail: pure backfill, no reversible schema change
    }
};
