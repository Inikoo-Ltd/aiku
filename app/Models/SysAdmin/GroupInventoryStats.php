<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Mar 2024 11:36:11 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SysAdmin\GroupInventoryStats
 *
 * @property int $id
 * @property int $group_id
 * @property int $number_trade_units
 * @property int $number_warehouses
 * @property int $number_warehouses_state_in_process
 * @property int $number_warehouses_state_open
 * @property int $number_warehouses_state_closing_down
 * @property int $number_warehouses_state_closed
 * @property int $number_warehouse_areas
 * @property int $number_locations
 * @property int $number_locations_status_operational
 * @property int $number_locations_status_broken
 * @property int $number_empty_locations
 * @property int $number_locations_no_stock_slots
 * @property int $number_locations_allow_stocks
 * @property int $number_locations_allow_fulfilment
 * @property int $number_locations_allow_dropshipping
 * @property string $stock_value
 * @property string $stock_commercial_value
 * @property int $number_stock_families
 * @property int $number_current_stock_families active + discontinuing
 * @property int $number_stock_families_state_in_process
 * @property int $number_stock_families_state_active
 * @property int $number_stock_families_state_discontinuing
 * @property int $number_stock_families_state_discontinued
 * @property int $number_stocks
 * @property int $number_current_stocks active + discontinuing
 * @property int $number_stocks_state_in_process
 * @property int $number_stocks_state_active
 * @property int $number_stocks_state_discontinuing
 * @property int $number_stocks_state_discontinued
 * @property int $number_stocks_state_suspended
 * @property int $number_deliveries
 * @property int $number_deliveries_type_order
 * @property int $number_deliveries_type_replacement
 * @property int $number_deliveries_state_unassigned
 * @property int $number_deliveries_state_queued
 * @property int $number_deliveries_state_handling
 * @property int $number_deliveries_state_handling_blocked
 * @property int $number_deliveries_state_packed
 * @property int $number_deliveries_state_finalised
 * @property int $number_deliveries_state_dispatched
 * @property int $number_deliveries_state_cancelled
 * @property int $number_deliveries_cancelled_at_state_unassigned
 * @property int $number_deliveries_cancelled_at_state_queued
 * @property int $number_deliveries_cancelled_at_state_handling
 * @property int $number_deliveries_cancelled_at_state_handling_blocked
 * @property int $number_deliveries_cancelled_at_state_packed
 * @property int $number_deliveries_cancelled_at_state_finalised
 * @property int $number_deliveries_cancelled_at_state_dispatched
 * @property int $number_deliveries_cancelled_at_state_cancelled
 * @property int $number_org_stock_audits
 * @property int $number_org_stock_audits_state_in_process
 * @property int $number_org_stock_audits_state_completed
 * @property int $number_org_stock_audit_deltas
 * @property int $number_org_stock_audit_delta_type_addition
 * @property int $number_org_stock_audit_delta_type_subtraction
 * @property int $number_org_stock_audit_delta_type_no_change
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static Builder<static>|GroupInventoryStats newModelQuery()
 * @method static Builder<static>|GroupInventoryStats newQuery()
 * @method static Builder<static>|GroupInventoryStats query()
 * @mixin Eloquent
 */
class GroupInventoryStats extends Model
{
    protected $table = 'group_inventory_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
