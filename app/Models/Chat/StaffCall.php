<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A voice, video and screen call in a staff conversation or a meeting, carried by LiveKit. Only one call per conversation
 * and one per meeting is live (ended_at null), enforced by the partial unique indexes staff_calls_one_live_per_conversation
 * and staff_calls_one_live_per_meeting. A meeting call may have no conversation at all.
 *
 * @property int $id
 * @property string $ulid
 * @property int $group_id
 * @property int|null $staff_conversation_id
 * @property int|null $staff_meeting_id
 * @property int $started_by_user_id
 * @property string $room
 * @property Carbon $started_at
 * @property Carbon|null $answered_at
 * @property Carbon|null $ended_at
 * @property string|null $outcome
 * @property int|null $declined_by_user_id
 * @property-read StaffConversation|null $conversation
 * @property-read StaffMeeting|null $meeting
 * @property-read User $startedBy
 */
class StaffCall extends Model
{
    public const string OUTCOME_ANSWERED = 'answered';
    public const string OUTCOME_DECLINED = 'declined';
    public const string OUTCOME_MISSED = 'missed';
    public const string OUTCOME_CANCELLED = 'cancelled';

    public const string KIND_VOICE = 'voice';
    public const string KIND_VIDEO = 'video';
    public const string KIND_SCREEN = 'screen';

    /** How the starter began the call: what the ring says, and what the answering browser turns on first. */
    public const array KINDS = [self::KIND_VOICE, self::KIND_VIDEO, self::KIND_SCREEN];

    public const int RING_SECONDS = 30;

    public const int PING_SECONDS = 20;

    public const int STALE_SECONDS = 60;

    public const int TOKEN_TTL_SECONDS = 120;

    /** A call nobody is seated in yet is left alone this long, so a sweep never ends it between creating it and seating its starter. */
    public const int SEAT_GRACE_SECONDS = 15;

    public const int RING_TIMEOUT_GRACE_SECONDS = 5;

    public const int MIN_SECRET_BYTES = 32;

    public const string ENTRY_PREFIX = 'staff-call:';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at'  => 'datetime',
            'answered_at' => 'datetime',
            'ended_at'    => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StaffCall $call) {
            $call->ulid ??= (string) Str::ulid();
            $call->room ??= 'staff-call-'.Str::random(40);
            $call->started_at ??= now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Off unless the server address, key and a secret long enough to sign with (HS256 needs 32 bytes) are all set.
     */
    public static function isEnabled(): bool
    {
        return filled(config('services.livekit.url'))
            && filled(config('services.livekit.api_key'))
            && strlen((string) config('services.livekit.api_secret')) >= self::MIN_SECRET_BYTES;
    }

    /**
     * What a call entry keeps as its message body: a neutral marker, put into words in the reader's language by entryText().
     */
    public static function entryMarker(string $outcome, ?int $durationSeconds, string $kind = self::KIND_VOICE): string
    {
        return self::ENTRY_PREFIX.$outcome.($outcome === self::OUTCOME_ANSWERED ? ':'.(int) $durationSeconds : '').($kind === self::KIND_SCREEN ? ':screen' : '');
    }

    /** The only call entries that count as unread: a missed call or a missed screen share. */
    public const array MISSED_ENTRY_MARKERS = [self::ENTRY_PREFIX.self::OUTCOME_MISSED, self::ENTRY_PREFIX.self::OUTCOME_MISSED.':screen'];

    public static function isMissedEntry(?string $body): bool
    {
        return in_array($body, self::MISSED_ENTRY_MARKERS, true);
    }

    public static function entryText(?string $body): ?string
    {
        if (!$body || !str_starts_with($body, self::ENTRY_PREFIX)) {
            return $body;
        }

        $parts    = explode(':', substr($body, strlen(self::ENTRY_PREFIX)));
        $outcome  = $parts[0];
        $isScreen = in_array('screen', $parts, true);
        $seconds  = $outcome === self::OUTCOME_ANSWERED ? (int) ($parts[1] ?? 0) : 0;
        $duration = $seconds < 60 ? __(':seconds s', ['seconds' => $seconds]) : __(':minutes min', ['minutes' => (int) round($seconds / 60)]);

        if ($isScreen) {
            return match ($outcome) {
                self::OUTCOME_ANSWERED => __('Screen share, :duration', ['duration' => $duration]),
                self::OUTCOME_DECLINED => __('Screen share declined'),
                self::OUTCOME_MISSED   => __('Missed screen share'),
                default                => __('Screen share cancelled'),
            };
        }

        return match ($outcome) {
            self::OUTCOME_ANSWERED => __('Call, :duration', ['duration' => $duration]),
            self::OUTCOME_DECLINED => __('Call declined'),
            self::OUTCOME_MISSED   => __('Missed call'),
            default                => __('Call cancelled'),
        };
    }

    public static function canBeHeldIn(StaffConversation $conversation): bool
    {
        return $conversation->type === 'dm' && $conversation->context_type === null;
    }

    /**
     * The only answer to "may this person be in this conversation's call": an active user of the same group who is
     * still in the conversation now. Task supervisors who read a thread without being in it, and anyone who left, are out.
     */
    public static function userMayCallIn(StaffConversation $conversation, User $user): bool
    {
        return self::isEnabled()
            && $user->status
            && $user->group_id === $conversation->group_id
            && self::canBeHeldIn($conversation)
            && $conversation->isActiveParticipant($user);
    }

    /**
     * In the call's conversation, or any active colleague of the group once the call has a meeting link: sharing the
     * link is the invitation.
     */
    public function mayBeJoinedBy(User $user): bool
    {
        if ($this->ended_at !== null) {
            return false;
        }

        if ($this->conversation && self::userMayCallIn($this->conversation, $user)) {
            return true;
        }

        return self::isEnabled()
            && $this->staff_meeting_id !== null
            && $this->meeting?->ended_at === null
            && $user->status
            && $user->group_id === $this->group_id;
    }

    /** A one-to-one call with no meeting link: it ends as soon as either person leaves. */
    public function isDirect(): bool
    {
        return $this->conversation?->type === 'dm' && $this->staff_meeting_id === null;
    }

    /**
     * An answered call held in a conversation ends once nobody to talk to is left: one person alone with no guest, or
     * nobody of the conversation itself (a linked DM call never outlives the two people it belongs to).
     */
    public function shouldEndWhenSomeoneLeaves(): bool
    {
        if (!$this->conversation || $this->answered_at === null) {
            return false;
        }

        $present = $this->presentUsers()->pluck('users.id');
        $guests  = $this->guests()->where('status', StaffMeetingGuest::STATUS_ADMITTED)->exists();

        if ($present->count() + ($guests ? 1 : 0) < 2) {
            return true;
        }

        return !$this->conversation->activeParticipants()->whereIn('users.id', $present)->exists();
    }

    /** Staff told about this call: the conversation's people, everyone who has been in it, and whoever made its link. */
    public function watcherIds(): array
    {
        $ids = $this->conversation
            ? $this->conversation->activeParticipants()->where('users.status', true)->pluck('users.id')->all()
            : [];

        $ids = array_merge($ids, $this->users()->where('users.status', true)->pluck('users.id')->all());

        if ($this->meeting) {
            $ids[] = $this->meeting->created_by_user_id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(StaffConversation::class, 'staff_conversation_id');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(StaffMeeting::class, 'staff_meeting_id');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(StaffMeetingGuest::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff_call_participants')
            ->withPivot('joined_at', 'left_at', 'last_seen_at')
            ->withTimestamps();
    }

    public function presentUsers(): BelongsToMany
    {
        return $this->users()->wherePivotNull('left_at');
    }

    /**
     * What browsers are told about a call. Never the room name: that comes only with a token.
     *
     * @return array<string, mixed>
     */
    public function toBroadcast(): array
    {
        $this->loadMissing(['conversation', 'meeting', 'startedBy', 'users']);

        $waitingGuests = $this->meeting
            ? $this->meeting->guests()->where('status', StaffMeetingGuest::STATUS_WAITING)->orderBy('id')->get(['ulid', 'name'])
            : collect();
        $guestsIn = $this->guests()->where('status', StaffMeetingGuest::STATUS_ADMITTED)->orderBy('id')->get(['ulid', 'name']);

        return [
            'ulid'               => $this->ulid,
            'conversation_ulid'  => $this->conversation?->ulid,
            'conversation_type'  => $this->conversation?->type ?? 'meeting',
            'kind'               => $this->kind ?? self::KIND_VOICE,
            'conversation_name'  => $this->conversation ? $this->conversation->name : $this->meeting?->title,
            'is_direct'          => $this->isDirect(),
            'meeting_url'        => $this->meeting?->url(),
            'meeting_locked'     => $this->meeting?->locked_at !== null,
            'guests_waiting'     => $waitingGuests->map(fn (StaffMeetingGuest $guest) => ['ulid' => $guest->ulid, 'name' => $guest->name])->values()->all(),
            'guests_in'          => $guestsIn->map(fn (StaffMeetingGuest $guest) => ['ulid' => $guest->ulid, 'name' => $guest->name])->values()->all(),
            'started_by_user_id' => $this->started_by_user_id,
            'started_by_name'    => $this->startedBy->chatName(),
            'started_at'         => $this->started_at?->toIso8601ZuluString(),
            'elapsed_seconds'    => $this->started_at ? (int) $this->started_at->diffInSeconds(now()) : 0,
            'answered_at'        => $this->answered_at?->toIso8601ZuluString(),
            'ended_at'           => $this->ended_at?->toIso8601ZuluString(),
            'outcome'            => $this->outcome,
            'present_user_ids'   => $this->users->filter(fn (User $user) => $user->pivot->left_at === null)->pluck('id')->values()->all(),
            'joined_user_ids'    => $this->users->pluck('id')->values()->all(),
        ];
    }

    /**
     * @return array{ulid: string, outcome: string|null, kind: string, started_by_user_id: int, duration_seconds: int|null, joined: array<int, string>}
     */
    public function entrySummary(): array
    {
        $this->loadMissing('users');

        return [
            'ulid'               => $this->ulid,
            'outcome'            => $this->outcome,
            'kind'               => $this->kind ?? self::KIND_VOICE,
            'started_by_user_id' => $this->started_by_user_id,
            'duration_seconds'   => $this->answered_at && $this->ended_at ? (int) $this->answered_at->diffInSeconds($this->ended_at) : null,
            'joined'             => $this->users->map(fn (User $user) => $user->chatName())->values()->all(),
        ];
    }
}
