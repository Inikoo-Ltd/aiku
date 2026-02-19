<?php

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $work_schedule_id
 * @property int $day_of_week
 * @property bool $is_working_day
 * @property string|null $start_time
 * @property string|null $end_time
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\HumanResources\WorkScheduleBreak> $breaks
 * @property-read \App\Models\HumanResources\WorkSchedule $workSchedule
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkScheduleDay newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkScheduleDay newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkScheduleDay query()
 * @mixin \Eloquent
 */
class WorkScheduleDay extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'day_of_week'    => 'integer',
        'is_working_day' => 'boolean',
        'start_time'     => 'string',
        'end_time'       => 'string',
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
