<?php

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $restricted_period_id
 * @property string $target_type
 * @property int $target_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\HumanResources\RestrictedPeriod $restrictedPeriod
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RestrictedPeriodTarget newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RestrictedPeriodTarget newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RestrictedPeriodTarget query()
 * @mixin \Eloquent
 */
class RestrictedPeriodTarget extends Model
{
    protected $guarded = [];

    public function restrictedPeriod(): BelongsTo
    {
        return $this->belongsTo(RestrictedPeriod::class);
    }
}
