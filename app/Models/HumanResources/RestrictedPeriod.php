<?php

namespace App\Models\HumanResources;

use App\Models\SysAdmin\Organisation;
use DateTimeInterface;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
