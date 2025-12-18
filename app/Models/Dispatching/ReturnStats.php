<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 13:50:00 Makassar Time
 * Description: ReturnStats model for return statistics
 */

namespace App\Models\Dispatching;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Dispatching\ReturnStats
 *
 * @property int $id
 * @property int $return_id
 * @property int $number_items current number of items
 * @property int $number_items_state_pending
 * @property int $number_items_state_received
 * @property int $number_items_state_inspecting
 * @property int $number_items_state_accepted
 * @property int $number_items_state_rejected
 * @property int $number_items_state_restocked
 * @property string $total_quantity_expected
 * @property string $total_quantity_received
 * @property string $total_quantity_accepted
 * @property string $total_quantity_rejected
 * @property string $total_quantity_restocked
 * @property string $total_refund_amount
 * @property string $total_org_refund_amount
 * @property string $total_grp_refund_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Dispatching\OrderReturn $return
 * @method static Builder<static>|ReturnStats newModelQuery()
 * @method static Builder<static>|ReturnStats newQuery()
 * @method static Builder<static>|ReturnStats query()
 * @mixin Eloquent
 */
class ReturnStats extends Model
{
    protected $table = 'return_stats';

    protected $guarded = [];

    public function return(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }
}
