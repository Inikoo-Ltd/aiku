<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 13:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A public Slack channel whose messages are kept in aiku.
 *
 * @property int $id
 * @property int $group_id
 * @property string $slack_id
 * @property string|null $name
 * @property bool $is_archived
 * @property bool $is_member
 * @property bool $is_public
 * @property bool $is_ignored
 * @property \Illuminate\Support\Carbon|null $deleted_in_slack_at
 * @property array|null $data
 * @property string|null $history_backfilled_to
 * @property \Illuminate\Support\Carbon|null $history_synced_at
 */
class SlackChannel extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'is_public' => true,
        'is_ignored' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_archived'       => 'boolean',
            'is_member'         => 'boolean',
            'is_public'         => 'boolean',
            'is_ignored'        => 'boolean',
            'deleted_in_slack_at' => 'datetime',
            'data'              => 'array',
            'history_synced_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SlackMessage::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(SlackChannelBookmark::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(SlackChannelMember::class);
    }

    public function scopeKept(Builder $query): Builder
    {
        return $query->where('is_public', true)->where('is_ignored', false)->whereNull('deleted_in_slack_at');
    }

    /**
     * The only answer to "may this aiku user see this Slack channel": every screen, search, file download and API
     * asks it through this scope or isVisibleTo, never by hand.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->kept()->where('group_id', $user->group_id);
        if (!self::userMayReadPublicChannels($user)) {
            $query->whereRaw('false');
        }

        return $query;
    }

    public function isVisibleTo(User $user): bool
    {
        return self::visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /**
     * Public channels are open to every member of the Slack workspace, so an active aiku user linked to an active
     * Slack person may read them. The link counts only when both hold the same email, as anyone can type a Slack id
     * into their own profile, and never for a Slack guest. Engineers see them without a Slack account, as they build and
     * review these screens. To open them to all staff instead, delete the condition in the brackets.
     */
    public static function userMayReadPublicChannels(User $user): bool
    {
        return $user->status
            && !$user->worksOnlyForAgents()
            && (Ticket::canBeManagedBy($user) || SlackUser::where('user_id', $user->id)->where('is_bot', false)->where('is_deleted', false)
                ->whereRaw('lower(email) = ?', [strtolower((string) $user->email)])
                ->whereRaw("coalesce((data->>'is_restricted')::boolean, false) = false")
                ->exists());
    }
}
