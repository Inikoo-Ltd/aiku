<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 12:12:37 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Models\Inventory;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Inventory\WarehouseStats
 *
 * @property int $id
 * @property int $warehouse_id
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
 * @property int $number_org_stock_movements
 * @property int $number_org_stock_movements_type_purchase
 * @property int $number_org_stock_movements_type_return_dispatch
 * @property int $number_org_stock_movements_type_return_picked
 * @property int $number_org_stock_movements_type_return_consumption
 * @property int $number_org_stock_movements_type_picked
 * @property int $number_org_stock_movements_type_location_transfer
 * @property int $number_org_stock_movements_type_found
 * @property int $number_org_stock_movements_type_consumption
 * @property int $number_org_stock_movements_type_write_off
 * @property int $number_org_stock_movements_type_adjustment
 * @property int $number_org_stock_movements_type_associate
 * @property int $number_org_stock_movements_type_disassociate
 * @property int $number_org_stock_movements_flow_in
 * @property int $number_org_stock_movements_flow_out
 * @property int $number_org_stock_movements_flow_no_change
 * @property int $number_org_stock_audits
 * @property int $number_org_stock_audits_state_in_process
 * @property int $number_org_stock_audits_state_completed
 * @property int $number_org_stock_audit_deltas
 * @property int $number_org_stock_audit_delta_type_addition
 * @property int $number_org_stock_audit_delta_type_subtraction
 * @property int $number_org_stock_audit_delta_type_no_change
 * @property int $number_fulfilments
 * @property int $number_customers_interest_pallets_storage
 * @property int $number_customers_interest_items_storage
 * @property int $number_customers_interest_dropshipping
 * @property int $number_customers_status_no_rental_agreement
 * @property int $number_customers_status_active
 * @property int $number_customers_status_unaccomplished
 * @property int $number_customers_status_inactive
 * @property int $number_customers_status_lost
 * @property int $number_customers_with_stored_items
 * @property int $number_customers_with_pallets
 * @property int $number_customers_with_stored_items_state_submitted
 * @property int $number_customers_with_stored_items_state_in_process
 * @property int $number_customers_with_stored_items_state_active
 * @property int $number_customers_with_stored_items_state_discontinuing
 * @property int $number_customers_with_stored_items_state_discontinued
 * @property int $number_pallets
 * @property int $number_pallets_with_stored_items
 * @property int $number_pallets_type_pallet
 * @property int $number_pallets_type_box
 * @property int $number_pallets_type_oversize
 * @property int $number_pallets_state_in_process
 * @property int $number_pallets_with_stored_items_state_in_process
 * @property int $number_pallets_state_submitted
 * @property int $number_pallets_with_stored_items_state_submitted
 * @property int $number_pallets_state_confirmed
 * @property int $number_pallets_with_stored_items_state_confirmed
 * @property int $number_pallets_state_received
 * @property int $number_pallets_with_stored_items_state_received
 * @property int $number_pallets_state_booking_in
 * @property int $number_pallets_with_stored_items_state_booking_in
 * @property int $number_pallets_state_booked_in
 * @property int $number_pallets_with_stored_items_state_booked_in
 * @property int $number_pallets_state_not_received
 * @property int $number_pallets_with_stored_items_state_not_received
 * @property int $number_pallets_state_storing
 * @property int $number_pallets_with_stored_items_state_storing
 * @property int $number_pallets_state_request_return_in_process
 * @property int $number_pallets_with_stored_items_state_request_return
 * @property int $number_pallets_state_picking
 * @property int $number_pallets_with_stored_items_state_picking
 * @property int $number_pallets_state_picked
 * @property int $number_pallets_with_stored_items_state_picked
 * @property int $number_pallets_state_damaged
 * @property int $number_pallets_with_stored_items_state_damaged
 * @property int $number_pallets_state_lost
 * @property int $number_pallets_with_stored_items_state_lost
 * @property int $number_pallets_state_other_incident
 * @property int $number_pallets_with_stored_items_state_other_incident
 * @property int $number_pallets_state_dispatched
 * @property int $number_pallets_with_stored_items_state_dispatched
 * @property int $number_pallets_status_in_process
 * @property int $number_pallets_with_stored_items_status_in_process
 * @property int $number_pallets_status_receiving
 * @property int $number_pallets_with_stored_items_status_receiving
 * @property int $number_pallets_status_not_received
 * @property int $number_pallets_with_stored_items_status_not_received
 * @property int $number_pallets_status_storing
 * @property int $number_pallets_with_stored_items_status_storing
 * @property int $number_pallets_status_returning
 * @property int $number_pallets_with_stored_items_status_returning
 * @property int $number_pallets_status_returned
 * @property int $number_pallets_with_stored_items_status_returned
 * @property int $number_pallets_status_incident
 * @property int $number_pallets_with_stored_items_status_incident
 * @property int $number_stored_items
 * @property int $number_stored_items_state_submitted
 * @property int $number_stored_items_state_in_process
 * @property int $number_stored_items_state_active
 * @property int $number_stored_items_state_discontinuing
 * @property int $number_stored_items_state_discontinued
 * @property int $number_pallet_deliveries
 * @property int $number_pallet_deliveries_state_in_process
 * @property int $number_pallet_deliveries_state_submitted
 * @property int $number_pallet_deliveries_state_confirmed
 * @property int $number_pallet_deliveries_state_received
 * @property int $number_pallet_deliveries_state_not_received
 * @property int $number_pallet_deliveries_state_booking_in
 * @property int $number_pallet_deliveries_state_booked_in
 * @property int $number_pallet_returns
 * @property int $number_pallet_returns_state_in_process
 * @property int $number_pallet_returns_state_submitted
 * @property int $number_pallet_returns_state_confirmed
 * @property int $number_pallet_returns_state_picking
 * @property int $number_pallet_returns_state_picked
 * @property int $number_pallet_returns_state_dispatched
 * @property int $number_pallet_returns_state_consolidated
 * @property int $number_pallet_returns_state_cancel
 * @property int $number_stored_item_audits
 * @property int $number_stored_item_audits_state_in_process
 * @property int $number_stored_item_audits_state_completed
 * @property int $number_recurring_bills
 * @property int $number_recurring_bills_status_current
 * @property int $number_recurring_bills_status_former
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $total_volume cbm
 * @property string $total_weight kg
 * @property int $number_pallets_state_request_return_submitted
 * @property int $number_pallets_state_request_return_confirmed
 * @property int $number_customers_status_pre_registration
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @method static Builder<static>|WarehouseStats newModelQuery()
 * @method static Builder<static>|WarehouseStats newQuery()
 * @method static Builder<static>|WarehouseStats query()
 * @mixin Eloquent
 */
class WarehouseStats extends Model
{
    protected $table = 'warehouse_stats';

    protected $guarded = [];



    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
