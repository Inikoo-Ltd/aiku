<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
 * Description: ReturnStats model for return statistics
 */

namespace App\Models\GoodsIn;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\GoodsIn\ReturnStats
 *
 * @property int $id
 * @property int $return_id
 * @property int $number_items current number of items
 * @property int $number_items_state_waiting_to_receive
 * @property int $number_items_state_received
 * @property int $number_items_state_inspected
 * @property int $number_items_state_restocked
 * @property int $number_items_state_cancelled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\GoodsIn\OrderReturn $return
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
