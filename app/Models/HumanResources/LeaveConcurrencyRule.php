<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Concurrency\LeaveConcurrencyRuleTypeEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $name
 * @property LeaveConcurrencyRuleTypeEnum $rule_type
 * @property int|null $limit
 * @property int $max_overlap_days
 * @property bool $is_active
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $createdBy
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HumanResources\LeaveConcurrencyTarget> $targets
 * @property-read User|null $updatedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveConcurrencyRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveConcurrencyRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveConcurrencyRule query()
 * @mixin \Eloquent
 */
class LeaveConcurrencyRule extends Model
{
    use InOrganisation;

    protected $guarded = [];

    protected $casts = [
        'rule_type'         => LeaveConcurrencyRuleTypeEnum::class,
        'limit'             => 'integer',
        'max_overlap_days'  => 'integer',
        'is_active'         => 'boolean',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(LeaveConcurrencyTarget::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
