<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Concurrency\LeaveConcurrencyRuleTypeEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
