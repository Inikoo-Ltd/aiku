<?php

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $variant_id
 * @property string $frequency
 * @property string|null $from
 * @property string|null $to
 * @property string|null $data
 * @property int $number_records
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Catalogue\Variant $variant
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VariantTimeSeries newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VariantTimeSeries newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VariantTimeSeries query()
 * @mixin \Eloquent
 */
class VariantTimeSeries extends Model
{
    protected $guarded = [];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }
}
