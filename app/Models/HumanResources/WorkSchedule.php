<?php

namespace App\Models\HumanResources;

use App\Models\Helpers\Timezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\Carbon;

class WorkSchedule extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    public function timezone(): BelongsTo
    {
        return $this->belongsTo(Timezone::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class);
    }

    public function isOpenNow(string $timezone): bool
    {
        $now = Carbon::now($timezone);
        $dayOfWeek = $now->dayOfWeekIso; // 1 (Mon) - 7 (Sun)

        $todaySchedule = $this->days()->where('day_of_week', $dayOfWeek)->first();

        if (!$todaySchedule || !$todaySchedule->is_working_day) {
            return false;
        }

        $start = Carbon::parse($todaySchedule->start_time, $timezone)->setDateFrom($now);
        $end = Carbon::parse($todaySchedule->end_time, $timezone)->setDateFrom($now);

        if ($end->lessThan($start)) {
            $end->addDay();
        }

        return $now->between($start, $end);
    }
}
