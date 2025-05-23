<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 20:29:37 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $number_orders_state_creating
 * @property string|null $latest_creating_order submitted_at, created_at for state=creating
 * @property string|null $oldest_creating_order submitted_at, created_at for state=creating
 * @property string|null $average_start_date_orders_state_creating based on submitted_at, created_at for state=creating
 * @property string|null $average_date_for_orders_in_state_creating
 * @property string $orders_state_creating_amount_grp_currency
 * @property int $number_orders_state_submitted
 * @property string|null $latest_submitted_order submitted_at, created_at for state=creating
 * @property string|null $oldest_submitted_order submitted_at, created_at for state=creating
 * @property string|null $average_start_date_orders_state_submitted based on submitted_at, created_at for state=creating
 * @property string|null $average_date_for_orders_in_state_submitted
 * @property string $orders_state_submitted_amount_grp_currency
 * @property int $number_orders_state_submitted_paid
 * @property string|null $latest_submitted_paid_order submitted_at, created_at for state=creating
 * @property string|null $oldest_submitted_paid_order submitted_at, created_at for state=creating
 * @property string|null $average_start_date_orders_state_submitted_paid based on submitted_at, created_at for state=creating
 * @property string|null $average_date_for_orders_in_state_submitted_paid
 * @property string $orders_state_submitted_paid_amount_grp_currency
 * @property int $number_orders_state_submitted_not_paid
 * @property string|null $latest_submitted_not_paid_order submitted_at, created_at for state=creating
 * @property string|null $oldest_submitted_not_paid_order submitted_at, created_at for state=creating
 * @property string|null $average_start_date_orders_state_submitted_not_paid based on submitted_at, created_at for state=creating
 * @property string|null $average_date_for_orders_in_state_submitted_not_paid
 * @property string $orders_state_submitted_not_paid_amount_grp_currency
 * @property int $number_orders_state_in_warehouse
 * @property string|null $latest_in_warehouse_order submitted_at, created_at for state=creating
 * @property string|null $oldest_in_warehouse_order submitted_at, created_at for state=creating
 * @property string|null $average_start_date_orders_state_in_warehouse based on submitted_at, created_at for state=creating
 * @property string|null $average_date_for_orders_in_state_in_warehouse
 * @property string $orders_state_in_warehouse_amount_grp_currency
 * @property int $number_orders_state_handling
 * @property string|null $latest_handling_order submitted_at, created_at for state=creating
 * @property string|null $oldest_handling_order submitted_at, created_at for state=creating
 * @property string|null $average_start_date_orders_state_handling based on submitted_at, created_at for state=creating
 * @property string|null $average_date_for_orders_in_state_handling
 * @property string $orders_state_handling_amount_grp_currency
 * @property int $number_orders_state_handling_blocked
 * @property string|null $latest_handling_blocked_order submitted_at, created_at for state=creating
 * @property string|null $oldest_handling_blocked_order submitted_at, created_at for state=creating
 * @property string|null $average_start_date_orders_state_handling_blocked based on submitted_at, created_at for state=creating
 * @property string|null $average_date_for_orders_in_state_handling_blocked
 * @property string $orders_state_handling_blocked_amount_grp_currency
 * @property int $number_orders_state_packed
 * @property string|null $latest_packed_order submitted_at, created_at for state=creating
 * @property string|null $oldest_packed_order submitted_at, created_at for state=creating
 * @property string|null $average_start_date_orders_state_packed based on submitted_at, created_at for state=creating
 * @property string|null $average_date_for_orders_in_state_packed
 * @property string $orders_state_packed_amount_grp_currency
 * @property int $number_orders_state_finalised
 * @property string|null $latest_finalised_order submitted_at, created_at for state=creating
 * @property string|null $oldest_finalised_order submitted_at, created_at for state=creating
 * @property string|null $average_start_date_orders_state_finalised based on submitted_at, created_at for state=creating
 * @property string|null $average_date_for_orders_in_state_finalised
 * @property string $orders_state_finalised_amount_grp_currency
 * @property int $number_orders_packed_today
 * @property string $orders_packed_today_amount_grp_currency
 * @property int $number_orders_finalised_today
 * @property string $orders_finalised_today_amount_grp_currency
 * @property int $number_orders_dispatched_today
 * @property string $orders_dispatched_today_amount_grp_currency
 * @property int $number_delivery_notes_state_queued
 * @property string|null $latest_queued_delivery_note
 * @property string|null $oldest_queued_delivery_note
 * @property string|null $average_start_date_delivery_notes_state_queued
 * @property string|null $average_date_for_delivery_notes_in_state_queued
 * @property string $weight_delivery_notes_state_queued
 * @property int $number_items_delivery_notes_state_queued
 * @property int $number_delivery_notes_state_handling
 * @property string|null $latest_handling_delivery_note
 * @property string|null $oldest_handling_delivery_note
 * @property string|null $average_start_date_delivery_notes_state_handling
 * @property string|null $average_date_for_delivery_notes_in_state_handling
 * @property string $weight_delivery_notes_state_handling
 * @property int $number_items_delivery_notes_state_handling
 * @property int $number_delivery_notes_state_handling_blocked
 * @property string|null $latest_handling_blocked_delivery_note
 * @property string|null $oldest_handling_blocked_delivery_note
 * @property string|null $average_start_date_delivery_notes_state_handling_blocked
 * @property string|null $average_date_for_delivery_notes_in_state_handling_blocked
 * @property string $weight_delivery_notes_state_handling_blocked
 * @property int $number_items_delivery_notes_state_handling_blocked
 * @property int $number_delivery_notes_state_packed
 * @property string|null $latest_packed_delivery_note
 * @property string|null $oldest_packed_delivery_note
 * @property string|null $average_start_date_delivery_notes_state_packed
 * @property string|null $average_date_for_delivery_notes_in_state_packed
 * @property string $weight_delivery_notes_state_packed
 * @property int $number_items_delivery_notes_state_packed
 * @property int $number_delivery_notes_state_finalised
 * @property string|null $latest_finalised_delivery_note
 * @property string|null $oldest_finalised_delivery_note
 * @property string|null $average_start_date_delivery_notes_state_finalised
 * @property string|null $average_date_for_delivery_notes_in_state_finalised
 * @property string $weight_delivery_notes_state_finalised
 * @property int $number_items_delivery_notes_state_finalised
 * @property int $number_delivery_notes_dispatched_today
 * @property string $weight_delivery_notes_dispatched_today
 * @property int $number_items_delivery_notes_dispatched_today
 * @property int $number_packings_state_queued
 * @property int $number_packings_state_packing
 * @property int $number_packings_state_packing_blocked
 * @property int $number_packings_done_today
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupOrderHandlingStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupOrderHandlingStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupOrderHandlingStats query()
 * @mixin \Eloquent
 */
class GroupOrderHandlingStats extends Model
{
    protected $table = 'group_order_handling_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
