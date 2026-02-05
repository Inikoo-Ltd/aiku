<?php

namespace App\Models\HumanResources;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
