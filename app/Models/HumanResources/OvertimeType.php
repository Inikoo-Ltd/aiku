<?php

namespace App\Models\HumanResources;

use App\Enums\HumanResources\Overtime\OvertimeCategoryEnum;
use App\Enums\HumanResources\Overtime\OvertimeCompensationTypeEnum;
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
 * @property OvertimeCategoryEnum $category
 * @property OvertimeCompensationTypeEnum $compensation_type
 * @property numeric|null $multiplier
 * @property array<array-key, mixed>|null $settings
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HumanResources\OvertimeRequest> $overtimeRequests
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OvertimeType query()
 * @mixin \Eloquent
 */
class OvertimeType extends Model
{
    use InOrganisation;

    protected $guarded = [];

    protected $casts = [
        'category'           => OvertimeCategoryEnum::class,
        'compensation_type'  => OvertimeCompensationTypeEnum::class,
        'multiplier'         => 'decimal:2',
        'settings'           => 'array',
        'is_active'          => 'boolean',
    ];

    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class);
    }
}
