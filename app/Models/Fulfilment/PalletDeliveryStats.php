<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Apr 2024 14:12:20 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\PalletDeliveryStats
 *
 * @property int $id
 * @property int $pallet_delivery_id
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
 * @property int $number_transactions
 * @property int $number_services
 * @property int $number_physical_goods
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_pallets_state_request_return_submitted
 * @property int $number_pallets_state_request_return_confirmed
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletDeliveryStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletDeliveryStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletDeliveryStats query()
 *
 * @mixin \Eloquent
 */
class PalletDeliveryStats extends Model
{
    protected $guarded = [];
}
