<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A recording someone started in a call (INI-586): each speaker's audio track is recorded on its own by LiveKit Egress,
 * transcribed, merged by time into one transcript and summarised. Transcript and summary are kept forever; the audio
 * is deleted once they are saved, unless whoever pressed Record chose to keep it for keep_audio_months.
 *
 * @property int $id
 * @property string $ulid
 * @property int $group_id
 * @property int $staff_call_id
 * @property string $status
 * @property int $started_by_user_id
 * @property int|null $stopped_by_user_id
 * @property int $keep_audio_months
 * @property Carbon $started_at
 * @property Carbon|null $stopped_at
 * @property Carbon|null $processed_at
 * @property array<int, array{at: int, speaker: string, text: string}>|null $transcript
 * @property array{summary?: string, decisions?: array<int, string>, action_items?: array<int, string>}|null $summary
 * @property Carbon|null $delete_audio_at
 * @property Carbon|null $audio_deleted_at
 * @property string|null $error
 * @property-read StaffCall $call
 * @property-read User $startedBy
 */
class StaffCallRecording extends Model
{
    public const string STATUS_STARTING = 'starting';
    public const string STATUS_RECORDING = 'recording';
    public const string STATUS_STOPPED = 'stopped';
    public const string STATUS_PROCESSING = 'processing';
    public const string STATUS_DONE = 'done';
    public const string STATUS_FAILED = 'failed';

    /** How long the audio may be kept when someone chooses to keep it; 0 deletes it once the transcript is saved. */
    public const array KEEP_AUDIO_MONTHS = [0, 1, 3, 6, 12];

    /** A stopped recording is processed with the tracks it has once Egress has had this long to report the files. */
    public const int TRACK_REPORT_GRACE_MINUTES = 15;

    public const string DISK = 'staff-chat-r2';

    /** A job killed this many times is marked failed instead of being run again (each run pays for transcription). */
    public const int MAX_ATTEMPTS = 3;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at'       => 'datetime',
            'stopped_at'       => 'datetime',
            'processed_at'     => 'datetime',
            'delete_audio_at'  => 'datetime',
            'audio_deleted_at' => 'datetime',
            'transcript'       => 'array',
            'summary'          => 'array',
            'user_ids'         => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StaffCallRecording $recording) {
            $recording->ulid ??= (string) Str::ulid();
            $recording->started_at ??= now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Off unless calls are on, the calls box runs Egress (LIVEKIT_RECORDING, set once it is installed), and there is a
     * key to transcribe with.
     */
    public static function isEnabled(): bool
    {
        return StaffCall::isEnabled()
            && (bool) config('services.livekit.recording')
            && filled(config('services.openai.api_key'));
    }

    /**
     * Start, stop and taking the mark off happen one at a time per call, so a Stop finishing late can never take the
     * mark off a recording someone started meanwhile.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function lockFor(StaffCall $call, callable $callback): mixed
    {
        return Cache::lock('staff-call-recording:'.$call->id, 120)->block(30, $callback);
    }

    /**
     * Staff who were in the call while it was being recorded (listed at Start and on each join until Stop, or their
     * voice is in it), or who are in its conversation now. Not who left before Record or joined after Stop; never guests.
     */
    public function isVisibleTo(User $user): bool
    {
        return $user->status
            && $user->group_id === $this->group_id
            && ($this->peopleRecorded()->where('users.id', $user->id)->exists() || (bool) $this->call->conversation?->isActiveParticipant($user));
    }

    /**
     * @return BelongsToMany<User, StaffCall>
     */
    public function peopleRecorded(): BelongsToMany
    {
        $voices = $this->tracks()->pluck('identity')->filter(fn (string $identity) => ctype_digit($identity))->map(fn (string $identity) => (int) $identity)->all();

        return $this->call->users()->whereIn('users.id', array_values(array_unique(array_merge($this->user_ids ?? [], $voices))));
    }

    /**
     * Someone joining while a recording runs is one of the people recorded. Atomic, so two joins never lose each other.
     */
    public static function noteJoined(StaffCall $call, User $user): void
    {
        DB::statement(
            "UPDATE staff_call_recordings SET user_ids = user_ids || to_jsonb(?::int), updated_at = now() WHERE staff_call_id = ? AND status = ? AND NOT user_ids @> to_jsonb(?::int)",
            [$user->id, $call->id, self::STATUS_RECORDING, $user->id]
        );
    }

    public function audioDirectory(): string
    {
        return 'call-recordings/'.$this->ulid;
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(StaffCall::class, 'staff_call_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(StaffCallRecordingTrack::class);
    }
}
