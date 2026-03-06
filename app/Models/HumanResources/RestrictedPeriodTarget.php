<?php

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestrictedPeriodTarget extends Model
{
    protected $table = 'restricted_period_targets';

    protected $guarded = [];

    public function restrictedPeriod(): BelongsTo
    {
        return $this->belongsTo(RestrictedPeriod::class);
    }
}
