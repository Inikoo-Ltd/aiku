<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 09:33:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\HumanResources;

use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $workplace_id
 * @property int|null $timesheet_id
 * @property string $subject_type Employee|Guest
 * @property int $subject_id
 * @property TimeTrackerStatusEnum $status
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property int|null $start_clocking_id
 * @property int|null $end_clocking_id
 * @property int|null $duration seconds
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Model|\Eloquent $subject
 * @property-read \App\Models\HumanResources\Timesheet|null $timesheet
 * @method static Builder<static>|TimeTracker newModelQuery()
 * @method static Builder<static>|TimeTracker newQuery()
 * @method static Builder<static>|TimeTracker onlyTrashed()
 * @method static Builder<static>|TimeTracker query()
 * @method static Builder<static>|TimeTracker withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|TimeTracker withoutTrashed()
 * @mixin \Eloquent
 */
class TimeTracker extends Model
{
    use SoftDeletes;

    protected $casts = [
        'status'    => TimeTrackerStatusEnum::class,
        'starts_at' => 'datetime:Y-m-d H:i:s',
        'ends_at'   => 'datetime:Y-m-d H:i:s'

    ];


    protected $guarded = [];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

}
