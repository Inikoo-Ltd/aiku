<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $group_id
 * @property string $ulid
 * @property string $type
 * @property string|null $name
 * @property string|null $dm_key
 * @property \Illuminate\Support\Carbon|null $last_message_at
 */
class StaffConversation extends Model
{
    public const string PRECISE_DATE_FORMAT = 'Y-m-d H:i:s.uP';

    protected $guarded = [];

    protected $dateFormat = self::PRECISE_DATE_FORMAT;

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (StaffConversation $conversation) {
            $conversation->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff_conversation_participants')
            ->withPivot('last_read_at', 'left_at')
            ->withTimestamps();
    }

    public function activeParticipants(): BelongsToMany
    {
        return $this->participants()->wherePivotNull('left_at');
    }

    /**
     * Joining again clears a previous leave, so the whole history is theirs to read once more.
     *
     * @param array<int, int> $userIds
     */
    public function addParticipants(array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (!$userIds) {
            return;
        }

        $this->participants()->syncWithoutDetaching($userIds);
        $this->participants()->newPivotStatement()
            ->where('staff_conversation_id', $this->id)
            ->whereIn('user_id', $userIds)
            ->whereNotNull('left_at')
            ->update(['left_at' => null]);
    }

    /**
     * Someone taken off keeps the history up to that moment but gets nothing new and cannot write.
     *
     * @param array<int, int> $userIds
     */
    public function removeParticipants(array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (!$userIds) {
            return;
        }

        $this->participants()->newPivotStatement()
            ->where('staff_conversation_id', $this->id)
            ->whereIn('user_id', $userIds)
            ->whereNull('left_at')
            ->update(['left_at' => now()->format(self::PRECISE_DATE_FORMAT)]);
    }

    public function leftAtFor(User $user): ?string
    {
        return $this->participants()->where('users.id', $user->id)->first()?->pivot?->left_at;
    }

    public function isActiveParticipant(User $user): bool
    {
        return $this->activeParticipants()->where('users.id', $user->id)->exists();
    }

    public function canBeWrittenToBy(User $user): bool
    {
        return $this->isActiveParticipant($user)
            || ($this->context_type === 'StaffTask' && $this->group_id === $user->group_id && StaffTask::isSupervisor($user));
    }

    public function messages(): HasMany
    {
        return $this->hasMany(StaffMessage::class);
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->where('users.id', $user->id)->exists();
    }

    /**
     * Supervisors read and write in any task thread without joining it, so they are not notified unless they subscribe.
     */
    public function canBeAccessedBy(User $user): bool
    {
        return $this->hasParticipant($user)
            || ($this->context_type === 'StaffTask' && $this->group_id === $user->group_id && StaffTask::isSupervisor($user));
    }

    public static function dmKey(array $userIds): string
    {
        sort($userIds);

        return implode('-', $userIds);
    }
}
