<?php

namespace App\Models\Dispatching;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $shipper_account_id
 * @property string|null $first_used_at
 * @property string|null $last_used_at
 * @property int $number_customers
 * @property int $number_delivery_notes
 * @property int $number_shipments
 * @property int $number_shipments_status_in_process
 * @property int $number_shipments_status_success
 * @property int $number_shipments_status_fixed
 * @property int $number_shipments_status_error
 * @property int $number_shipment_trackings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShipperAccountStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShipperAccountStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShipperAccountStats query()
 * @mixin \Eloquent
 */
class ShipperAccountStats extends Model
{
}
