<?php

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Masters\MasterCollection|null $masterCollection
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollectionOrderingIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollectionOrderingIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollectionOrderingIntervals query()
 * @mixin \Eloquent
 */
class MasterCollectionOrderingIntervals extends Model
{
    protected $table = 'master_collection_ordering_intervals';

    protected $guarded = [];

    public function masterCollection(): BelongsTo
    {
        return $this->belongsTo(MasterCollection::class);
    }
}
