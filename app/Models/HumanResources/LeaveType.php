<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Leave\LeaveCategoryEnum;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property string|null $description
 * @property LeaveCategoryEnum $category
 * @property bool $requires_approval
 * @property numeric|null $max_days_per_year
 * @property array<array-key, mixed>|null $settings
 * @property bool $is_active
 * @property bool $ignore_concurrency_leave_rules
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HumanResources\Leave> $leaves
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveType query()
 * @mixin \Eloquent
 */
class LeaveType extends Model
{
    use InOrganisation;

    protected $guarded = [];

    protected $casts = [
        'category'                        => LeaveCategoryEnum::class,
        'requires_approval'               => 'boolean',
        'max_days_per_year'               => 'integer',
        'settings'                        => 'array',
        'is_active'                       => 'boolean',
        'ignore_concurrency_leave_rules'  => 'boolean',
    ];

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function getShortCodeAttribute(): string
    {
        return \App\Enums\HumanResources\Leave\LeaveTypeEnum::shortCodes()[$this->code] ?? '';
    }
}
