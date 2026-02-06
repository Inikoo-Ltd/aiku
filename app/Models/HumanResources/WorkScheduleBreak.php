<?php

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $work_schedule_day_id
 * @property string|null $break_name
 * @property \Illuminate\Support\Carbon $start_time
 * @property \Illuminate\Support\Carbon $end_time
 * @property bool $is_paid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\HumanResources\WorkScheduleDay $workScheduleDay
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkScheduleBreak newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkScheduleBreak newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkScheduleBreak query()
 * @mixin \Eloquent
 */
class WorkScheduleBreak extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_paid'    => 'boolean',
        'start_time' => 'datetime:H:i:s',
        'end_time'   => 'datetime:H:i:s',
    ];

    public function workScheduleDay(): BelongsTo
    {
        return $this->belongsTo(WorkScheduleDay::class);
    }
}
