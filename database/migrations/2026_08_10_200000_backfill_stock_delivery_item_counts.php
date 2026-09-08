<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up()
    {
        DB::statement("
            update stock_deliveries
            set number_stock_delivery_items = counts.total,
                number_stock_delivery_items_except_cancelled = counts.total - counts.cancelled,
                number_stock_delivery_items_state_in_process = counts.in_process,
                number_stock_delivery_items_state_confirmed = counts.confirmed,
                number_stock_delivery_items_state_ready_to_ship = counts.ready_to_ship,
                number_stock_delivery_items_state_dispatched = counts.dispatched,
                number_stock_delivery_items_state_received = counts.received,
                number_stock_delivery_items_state_checked = counts.checked,
                number_stock_delivery_items_state_placed = counts.placed,
                number_stock_delivery_items_state_cancelled = counts.cancelled,
                number_stock_delivery_items_state_not_received = counts.not_received,
                number_stock_delivery_items_under_delivered = counts.under_delivered,
                number_stock_delivery_items_over_delivered = counts.over_delivered
            from (
                select
                    stock_delivery_id,
                    count(*) as total,
                    count(*) filter (where state = 'in_process') as in_process,
                    count(*) filter (where state = 'confirmed') as confirmed,
                    count(*) filter (where state = 'ready_to_ship') as ready_to_ship,
                    count(*) filter (where state = 'dispatched') as dispatched,
                    count(*) filter (where state = 'received') as received,
                    count(*) filter (where state = 'checked') as checked,
                    count(*) filter (where state = 'placed') as placed,
                    count(*) filter (where state = 'cancelled') as cancelled,
                    count(*) filter (where state = 'not_received') as not_received,
                    count(*) filter (where checked_at is not null and state != 'cancelled' and unit_quantity_checked < unit_quantity) as under_delivered,
                    count(*) filter (where checked_at is not null and state != 'cancelled' and unit_quantity_checked > unit_quantity) as over_delivered
                from stock_delivery_items
                where deleted_at is null
                group by stock_delivery_id
            ) counts
            where stock_deliveries.id = counts.stock_delivery_id
        ");
    }

    public function down()
    {
        // ponytail: pure backfill, no reversible schema change
    }
};
