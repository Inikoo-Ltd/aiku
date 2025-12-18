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
 * @property-read \App\Models\Dispatching\OrderReturn|null $return
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
