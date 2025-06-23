<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $master_collection_id
 * @property int $number_collections
 * @property int $number_collections_state_in_process
 * @property int $number_collections_state_open
 * @property int $number_collections_state_closing_down
 * @property int $number_collections_state_closed
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Masters\MasterCollection $masterCollection
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollectionStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollectionStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollectionStats query()
 * @mixin \Eloquent
 */
class MasterCollectionStats extends Model
{
    protected $table = 'master_collection_stats';

    public function masterCollection(): BelongsTo
    {
        return $this->belongsTo(MasterCollection::class);
    }
}
