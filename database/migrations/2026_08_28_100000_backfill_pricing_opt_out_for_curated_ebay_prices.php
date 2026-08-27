<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE portfolios p
            SET settings = jsonb_set(coalesce(p.settings, '{}'::jsonb), '{pricing_opt_out}', 'true')
            FROM customer_sales_channels csc, platforms pl, products pr
            WHERE csc.id = p.customer_sales_channel_id
              AND pl.id = csc.platform_id
              AND pl.type = 'ebay'
              AND pr.id = p.item_id
              AND p.item_type = 'Product'
              AND p.status = true
              AND p.customer_price::numeric IS DISTINCT FROM pr.rrp::numeric
        SQL);
    }

    public function down(): void
    {
    }
};
