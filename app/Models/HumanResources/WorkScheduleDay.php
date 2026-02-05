<?php

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkScheduleDay extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'day_of_week'    => 'integer',
        'is_working_day' => 'boolean',
        'start_time'     => 'datetime:H:i:s',
        'end_time'       => 'datetime:H:i:s',
    ];

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(WorkScheduleBreak::class);
    }
}
