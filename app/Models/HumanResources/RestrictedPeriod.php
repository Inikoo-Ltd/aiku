<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\Organisation;
use DateTimeInterface;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int|null $holiday_year_id
 * @property string $label
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property string $strictness
 * @property bool $is_active
 * @property bool $allow_superuser_override
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HumanResources\RestrictedException> $exceptions
 * @property-read \App\Models\HumanResources\HolidayYear|null $holidayYear
 * @property-read Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HumanResources\RestrictedPeriodTarget> $targets
 * @property-read User|null $updatedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RestrictedPeriod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RestrictedPeriod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RestrictedPeriod query()
 * @mixin \Eloquent
 */
class RestrictedPeriod extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date'              => 'date:Y-m-d',
        'end_date'                => 'date:Y-m-d',
        'is_active'               => 'boolean',
        'allow_superuser_override' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function holidayYear(): BelongsTo
    {
        return $this->belongsTo(HolidayYear::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(RestrictedPeriodTarget::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(RestrictedException::class);
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
