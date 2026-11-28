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
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property bool $is_private
 * @property string|null $direct_type
 * @property \Illuminate\Support\Carbon|null $deleted_in_slack_at
 * @property array|null $data
 * @property string|null $history_backfilled_to
 * @property \Illuminate\Support\Carbon|null $history_synced_at
 * @property int|null $brought_by_user_id
 * @property bool $is_listed
 * @property string|null $slack_team_id
 */
class SlackChannel extends Model
{
    /**
     * Slack's own name, except that a channel of another workspace whose name a main workspace channel already has
     * reads name-suffix (general-awa) in aiku (INI-540). Slack keeps its name; the column stores Slack's.
     */
    protected function name(): Attribute
    {
        return Attribute::get(function (?string $name) {
            if ($name === null || $this->slack_team_id === null) {
                return $name;
            }
            $suffix = \App\Actions\Chat\Staff\Slack\SlackWorkspace::forTeam($this->slack_team_id)?->nameSuffix;

            return $suffix && in_array($name, self::mainWorkspaceNames(), true) ? $name.'-'.$suffix : $name;
        });
    }

    /**
     * @return array<int, string>
     */
    private static function mainWorkspaceNames(): array
    {
        return once(fn () => self::kept()->whereNull('slack_team_id')->whereNotNull('name')->pluck('name')->all());
    }

    protected $guarded = [];

    protected $attributes = [
        'is_public' => true,
        'is_ignored' => false,
        'is_private' => false,
        'is_listed'  => false,
    ];

    protected function casts(): array
    {
        return [
            'is_archived'       => 'boolean',
            'is_member'         => 'boolean',
            'is_public'         => 'boolean',
            'is_ignored'        => 'boolean',
            'is_private'        => 'boolean',
            'is_listed'         => 'boolean',
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
     * Private channels brought in through the aiku bot (INI-431). Never part of kept(), so every public path stays closed to them;
     * they are read by their members only, through their own members-only path.
     */
    public function scopeKeptPrivate(Builder $query): Builder
    {
        return $query->where('is_private', true)->where('is_public', false)->whereNull('direct_type')->where('is_ignored', false)->whereNull('deleted_in_slack_at');
    }

    /**
     * Direct messages and group DMs brought by people who opted in (INI-431). Never part of kept() or keptPrivate(): they are
     * read by their members only, through directVisibleTo, and each message keeps its words only while its author has opted in.
     */
    public function scopeKeptDirect(Builder $query): Builder
    {
        return $query->whereNotNull('direct_type')->where('is_public', false)->where('is_ignored', false)->whereNull('deleted_in_slack_at');
    }

    /**
     * The only answer to "may this aiku user read this direct message": the same rule as privateVisibleTo, current membership,
     * no role bypass.
     */
    public function scopeDirectVisibleTo(Builder $query, User $user): Builder
    {
        $query->keptDirect()->where('group_id', $user->group_id);
        if (!self::privateReader($user)) {
            return $query->whereRaw('false');
        }

        return $query->whereIn('id', SlackChannelMember::whereIn('slack_user_id', self::linkedSlackIds($user))->select('slack_channel_id'));
    }

    public function isDirectVisibleTo(User $user): bool
    {
        return self::directVisibleTo($user)->whereKey($this->getKey())->exists();
    }

    public static function privateReader(User $user): ?SlackUser
    {
        return $user->status && !$user->worksOnlyForAgents() && \App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess::privateOptInOpen() ? self::linkedSlackPerson($user) : null;
    }

    /**
     * The only answer to "may this aiku user see this Slack channel": every screen, search, file download and API
     * asks it through this scope or isVisibleTo, never by hand.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->kept()->where('group_id', $user->group_id);
        if (!self::userMayReadPublicChannels($user)) {
            return $query->whereRaw('false');
        }
        if (Ticket::canBeManagedBy($user)) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query->whereNull('slack_team_id')->orWhereIn('slack_team_id', self::linkedSlackTeams($user)));
    }

    /**
     * Another workspace's public channels show only to people with an account there, as in Slack (INI-595).
     *
     * @return array<int, string>
     */
    private static function linkedSlackTeams(User $user): array
    {
        return self::linkedSlackPeople($user)->pluck('data.team_id')->filter()->unique()->values()->all();
    }

    public function isVisibleTo(User $user): bool
    {
        return self::visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /**
     * The only answer to "may this aiku user read this private Slack channel" (INI-435): an active aiku user linked to a
     * Slack person who is in slack_channel_members. No role bypasses it, engineers and group admins included.
     */
    public function scopePrivateVisibleTo(Builder $query, User $user): Builder
    {
        $query->keptPrivate()->where('group_id', $user->group_id);
        if (!self::privateReader($user)) {
            return $query->whereRaw('false');
        }

        return $query->whereIn('id', SlackChannelMember::whereIn('slack_user_id', self::linkedSlackIds($user))->select('slack_channel_id'));
    }

    public function isPrivateVisibleTo(User $user): bool
    {
        return self::privateVisibleTo($user)->whereKey($this->getKey())->exists();
    }

    /**
     * Private channels whose members chose to show their name (is_listed), offered to the other staff linked to Slack so they
     * can ask to join. Only the name goes out: reading stays on privateVisibleTo.
     */
    public function scopeListedTo(Builder $query, User $user): Builder
    {
        $query->keptPrivate()->where('is_listed', true)->where('group_id', $user->group_id);
        if (!self::privateReader($user)) {
            return $query->whereRaw('false');
        }

        return $query->whereNotIn('id', self::privateVisibleTo($user)->select('id'));
    }

    public function isListedTo(User $user): bool
    {
        return self::listedTo($user)->whereKey($this->getKey())->exists();
    }

    /**
     * Public channels by visibleTo, or private ones by privateVisibleTo. Only the paths that open one channel for reading
     * (stream, thread, versions, pins, files and links, read position) use it; lists, search, mentions, broadcasts and
     * writes stay on visibleTo, so a private channel never reaches them.
     */
    public function scopeReadableBy(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereIn('id', self::visibleTo($user)->select('id'))
            ->orWhereIn('id', self::privateVisibleTo($user)->select('id'))
            ->orWhereIn('id', self::directVisibleTo($user)->select('id')));
    }

    public function isReadableBy(User $user): bool
    {
        return self::readableBy($user)->whereKey($this->getKey())->exists();
    }

    /**
     * Public channels are open to every member of the Slack workspace, so an active aiku user linked to an active
     * Slack person may read them. The link counts only when both hold the same email or an engineer confirmed it by hand
     * (slack:link-users), as anyone can type a Slack id into their own profile, and never for a Slack guest. Engineers see them without a Slack account, as they build and
     * review these screens. To open them to all staff instead, delete the condition in the brackets.
     */
    public static function userMayReadPublicChannels(User $user): bool
    {
        return $user->status
            && !$user->worksOnlyForAgents()
            && (Ticket::canBeManagedBy($user) || self::linkedSlackPerson($user) !== null);
    }

    /**
     * The person's main Slack account: their main workspace one when they have it, else their account elsewhere.
     */
    public static function linkedSlackPerson(User $user): ?SlackUser
    {
        return self::linkedSlackPeople($user)->first();
    }

    /**
     * One Slack account per workspace (INI-540), the one linked by hand first: private and direct channels let them read
     * through any of them, never through a second account of the same workspace.
     *
     * @return array<int, string>
     */
    public static function linkedSlackIds(User $user): array
    {
        return self::linkedSlackPeople($user)->unique(fn (SlackUser $person) => $person->data['team_id'] ?? '')->pluck('slack_id')->values()->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, SlackUser>
     */
    private static function linkedSlackPeople(User $user): \Illuminate\Support\Collection
    {
        $otherTeams = array_map(fn ($workspace) => $workspace->teamId, \App\Actions\Chat\Staff\Slack\SlackWorkspace::others());

        return SlackUser::where('user_id', $user->id)->where('is_bot', false)->where('is_deleted', false)
            ->where(fn (Builder $query) => $query->whereNotNull('link_confirmed_at')->orWhereRaw('lower(email) = ?', [strtolower((string) $user->email)]))
            ->whereRaw("coalesce((data->>'is_restricted')::boolean, false) = false")
            ->orderByRaw('link_confirmed_at is null')->orderBy('id')
            ->get()
            ->sortBy(fn (SlackUser $person) => in_array($person->data['team_id'] ?? null, $otherTeams, true) ? 1 : 0)
            ->values();
    }
}
