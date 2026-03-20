<?php

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Catalogue\Collection|null $collection
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionOrderingIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionOrderingIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionOrderingIntervals query()
 * @mixin \Eloquent
 */
class CollectionOrderingIntervals extends Model
{
    protected $table = 'collection_ordering_intervals';

    protected $guarded = [];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
