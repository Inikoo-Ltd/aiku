<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Someone with no aiku login who knocked on a meeting link. They are never a user: they get one call and nothing else,
 * and their browser proves who it is with a secret only it holds (secret_hash keeps its sha256).
 *
 * @property int $id
 * @property string $ulid
 * @property int $staff_meeting_id
 * @property int|null $staff_call_id
 * @property string $name
 * @property string $secret_hash
 * @property string $status
 * @property string|null $ip
 * @property int|null $decided_by_user_id
 * @property Carbon|null $decided_at
 * @property Carbon|null $joined_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $left_at
 * @property-read StaffMeeting $meeting
 * @property-read StaffCall|null $call
 */
class StaffMeetingGuest extends Model
{
    public const string STATUS_WAITING = 'waiting';
    public const string STATUS_ADMITTED = 'admitted';
    public const string STATUS_DENIED = 'denied';
    public const string STATUS_REMOVED = 'removed';
    public const string STATUS_LEFT = 'left';

    public const string IDENTITY_PREFIX = 'guest-';

    public const int POLL_SECONDS = 4;

    protected $guarded = [];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return [
            'decided_at'   => 'datetime',
            'joined_at'    => 'datetime',
            'last_seen_at' => 'datetime',
            'left_at'      => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StaffMeetingGuest $guest) {
            $guest->ulid ??= (string) Str::ulid();
            $guest->last_seen_at ??= now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function holdsSecret(?string $secret): bool
    {
        return is_string($secret) && $secret !== '' && hash_equals($this->secret_hash, self::hashSecret($secret));
    }

    public function identity(): string
    {
        return self::IDENTITY_PREFIX.$this->ulid;
    }

    public static function ulidFromIdentity(string $identity): ?string
    {
        return str_starts_with($identity, self::IDENTITY_PREFIX) ? substr($identity, strlen(self::IDENTITY_PREFIX)) : null;
    }

    public function isInCall(StaffCall $call): bool
    {
        return $this->status === self::STATUS_ADMITTED && $this->staff_call_id === $call->id && $call->ended_at === null;
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(StaffMeeting::class, 'staff_meeting_id');
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(StaffCall::class, 'staff_call_id');
    }
}
