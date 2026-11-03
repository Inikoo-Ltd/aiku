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
use App\Models\Traits\HasHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $production_id
 * @property int $user_id
 * @property int|null $employee_id
 * @property int|null $planned_minutes
 * @property int|null $minutes
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int|null $clock_out_clocking_id
 * @property int|null $clock_in_clocking_id
 * @property Carbon|null $left_open_after_shift_at
 * @property Carbon|null $corrected_at
 * @property int|null $corrected_by_user_id
 * @property Carbon|null $original_ended_at
 * @property-read User|null $correctedBy
 * @property-read Production $production
 * @property-read User $user
 * @property-read Employee|null $employee
 * @method static Builder<static>|ManufactureBreak open()
 */
class ManufactureBreak extends Model implements Auditable
{
    use HasHistory;

    protected array $auditEvents = [
        'updated',
        'deleted',
    ];

    protected array $auditInclude = [
        'ended_at',
        'minutes',
        'corrected_at',
    ];

    public const int DEFAULT_OVERRUN_TOLERANCE_MINUTES = 5;
    public const int MAX_MINUTES = 32767;
    public const int ISSUES_LOOKBACK_DAYS = 14;

    protected static function booted(): void
    {
        static::saved(fn (self $model) => BroadcastManufactureFloorChanged::dispatch($model->production_id));
    }

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'left_open_after_shift_at' => 'datetime',
        'corrected_at'             => 'datetime',
        'original_ended_at'        => 'datetime',
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

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by_user_id');
    }

    public static function overrunToleranceMinutes(Production $production): int
    {
        return (int) ($production->settings['break_overrun_tolerance_minutes'] ?? self::DEFAULT_OVERRUN_TOLERANCE_MINUTES);
    }

    public function actualMinutes(): int
    {
        return (int) round($this->started_at->diffInSeconds($this->ended_at ?? now()) / 60);
    }

    public function overrunMinutes(): int
    {
        return $this->planned_minutes === null ? 0 : max(0, $this->actualMinutes() - $this->planned_minutes);
    }

    public function isOverrun(int $toleranceMinutes): bool
    {
        return $this->overrunMinutes() > $toleranceMinutes;
    }

    public function secondsWithin(Carbon $from, Carbon $to): int
    {
        $start = $this->started_at->max($from);
        $end   = ($this->ended_at ?? now())->min($to);

        return max(0, (int) $start->diffInSeconds($end, false));
    }
}
