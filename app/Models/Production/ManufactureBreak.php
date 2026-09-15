<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 14:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Events\BroadcastManufactureFloorChanged;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property int $user_id
 * @property int|null $employee_id
 * @property int $planned_minutes
 * @property int|null $minutes
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property-read Production $production
 * @property-read User $user
 * @property-read Employee|null $employee
 * @method static Builder<static>|ManufactureBreak open()
 */
class ManufactureBreak extends Model
{
    protected static function booted(): void
    {
        static::saved(fn (self $model) => BroadcastManufactureFloorChanged::dispatch($model->production_id));
    }

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function plannedEndAt(): Carbon
    {
        return $this->started_at->copy()->addMinutes($this->planned_minutes);
    }

    public function secondsWithin(Carbon $from, Carbon $to): int
    {
        $start = $this->started_at->max($from);
        $end   = ($this->ended_at ?? now()->min($this->plannedEndAt()))->min($to);

        return max(0, (int) $start->diffInSeconds($end, false));
    }
}
