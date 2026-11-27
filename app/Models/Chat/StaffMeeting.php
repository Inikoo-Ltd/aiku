<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A meeting link: /meet/{code}. Anyone of staff with the link joins after logging in; anyone else knocks as a guest
 * and waits until someone in the call lets them in. A link copied from a running call (for_one_call) ends with it;
 * a link made ahead of time starts a new call each time someone arrives and lapses IDLE_DAYS after its last use.
 *
 * @property int $id
 * @property string $ulid
 * @property int $group_id
 * @property string $code
 * @property string|null $title
 * @property int $created_by_user_id
 * @property bool $for_one_call
 * @property Carbon|null $locked_at
 * @property Carbon $last_used_at
 * @property Carbon|null $ended_at
 * @property-read User $createdBy
 * @property-read StaffCall|null $liveCall
 */
class StaffMeeting extends Model
{
    public const int IDLE_DAYS = 30;

    public const int MAX_OPEN_PER_USER = 20;

    public const int MAX_WAITING_GUESTS = 10;

    public const int MAX_WAITING_GUESTS_PER_ADDRESS = 3;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'for_one_call' => 'boolean',
            'locked_at'    => 'datetime',
            'last_used_at' => 'datetime',
            'ended_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StaffMeeting $meeting) {
            $meeting->ulid ??= (string) Str::ulid();
            $meeting->code ??= Str::random(24);
            $meeting->last_used_at ??= now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null && $this->last_used_at->gt(now()->subDays(self::IDLE_DAYS));
    }

    public function url(): string
    {
        return route('grp.meet.show', $this->code);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(StaffCall::class);
    }

    public function liveCall(): HasOne
    {
        return $this->hasOne(StaffCall::class)->whereNull('ended_at');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(StaffMeetingGuest::class);
    }
}
