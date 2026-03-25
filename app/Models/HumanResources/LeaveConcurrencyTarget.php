<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Concurrency\LeaveConcurrencyTargetRoleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $leave_concurrency_rule_id
 * @property string $target_type
 * @property int $target_id
 * @property LeaveConcurrencyTargetRoleEnum|null $role
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\HumanResources\LeaveConcurrencyRule $rule
 * @property-read Model|\Eloquent $target
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveConcurrencyTarget newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveConcurrencyTarget newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveConcurrencyTarget query()
 * @mixin \Eloquent
 */
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
