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
 * @property string|null $multiplier
 * @property array<array-key, mixed>|null $settings
 * @property bool $is_active
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
