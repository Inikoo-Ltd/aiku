<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Concurrency\LeaveConcurrencyTargetRoleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LeaveConcurrencyTarget extends Model
{
    protected $guarded = [];

    protected $casts = [
        'role' => LeaveConcurrencyTargetRoleEnum::class,
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LeaveConcurrencyRule::class, 'leave_concurrency_rule_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo('target');
    }
}
