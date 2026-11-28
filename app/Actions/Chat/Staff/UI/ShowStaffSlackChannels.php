<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\UI;

use App\Actions\Chat\Staff\Slack\SlackWorkspace;
use App\Models\Chat\SlackConnection;
use App\Actions\Chat\Staff\Slack\Write\StartSlackConnection;
use App\Actions\Chat\Staff\Slack\FormatSlackMessages;
use App\Actions\Chat\Staff\StaffChatWallpaper;
use App\Actions\Chat\Staff\Slack\GetSlackChannelMessages;
use App\Actions\Chat\Staff\Slack\GetSlackChannelUnreadCounts;
use App\Actions\Chat\Staff\Slack\MarkSlackChannelRead;
use App\Actions\Chat\Staff\Slack\ResolveSlackEmoji;
use App\Actions\OrgAction;
use App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess;
use App\Actions\Chat\Staff\Slack\Write\SlackWriteClient;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackChannelBookmark;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The Slack side of the staff chat page, read only: the channels this user may see (SlackChannel::visibleTo) and
 * the message stream of one of them.
 */
class ShowStaffSlackChannels extends OrgAction
{
    use AsAction;

    protected ?SlackChannel $slackChannel = null;

    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function inSlackChannel(SlackChannel $slackChannel, ActionRequest $request): Group
    {
        if ($slackChannel->direct_type && $slackChannel->isDirectVisibleTo($request->user())) {
            throw new HttpResponseException(redirect()->route('grp.chat.staff.direct.slack.show', [...$request->query(), 'slackChannel' => $slackChannel->id]));
        }
        if ($slackChannel->is_private && $slackChannel->isPrivateVisibleTo($request->user())) {
            throw new HttpResponseException(redirect()->route('grp.chat.staff.groups.slack.show', [...$request->query(), 'slackChannel' => $slackChannel->id]));
        }
        abort_unless($slackChannel->isVisibleTo($request->user()), 403);

        $this->slackChannel = $slackChannel;
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    /**
     * A private channel opens on the Verbrations tab, for its members only (SlackChannel::privateVisibleTo).
     */
    public function inPrivateSlackChannel(SlackChannel $slackChannel, ActionRequest $request): Group
    {
        abort_unless($slackChannel->isPrivateVisibleTo($request->user()), 403);

        $this->slackChannel = $slackChannel;
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    /**
     * A Slack DM or group DM opens on the V·Ds tab, for its members only (SlackChannel::directVisibleTo); an opted-in member writes in it (INI-593).
     */
    public function inDirectSlackChannel(SlackChannel $slackChannel, ActionRequest $request): Group
    {
        abort_unless($slackChannel->isDirectVisibleTo($request->user()), 403);

        $this->slackChannel = $slackChannel;
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    /**
     * A DM is named after the other people in it, as Slack does; a DM with oneself after oneself.
     */
    public static function directName(SlackChannel $slackChannel, ?User $viewer): string
    {
        $mine   = $viewer ? SlackChannel::linkedSlackPerson($viewer)?->slack_id : null;
        $people = SlackUser::whereIn('slack_id', $slackChannel->members()->select('slack_user_id'))->get(['slack_id', 'display_name', 'real_name', 'name']);
        $others = $people->where('slack_id', '!=', $mine);

        return ($others->isEmpty() ? $people : $others)->map(fn (SlackUser $person) => $person->display_name ?: $person->real_name ?: $person->name ?: $person->slack_id)->sort()->implode(', ');
    }

    /**
     * Each channel with this person's unread count and read position; a channel seen for the first time starts read.
     *
     * @return list<array{id: int, slack_id: string, name: string|null, topic: string|null, is_archived: bool, workspace: string|null, last_message_at: string|null, unread_count: int, last_read_ts: string|null}>
     */
    public function channels(User $user, bool $private = false, bool $direct = false): array
    {
        $public   = !$direct && !$private;
        $channels = ($direct ? SlackChannel::directVisibleTo($user) : ($private ? SlackChannel::privateVisibleTo($user) : SlackChannel::visibleTo($user)))
            ->select(['id', 'slack_id', 'name', 'is_archived', 'data', 'slack_team_id'])
            ->when($public, function (Builder $query) use ($user) {
                $inList = $this->inListQuery($user);
                $query->selectRaw('slack_channels.id in ('.$inList->toSql().') as in_list', $inList->getBindings());
            })
            ->addSelect(['last_message_at' => SlackMessage::select('sent_at')
                ->whereColumn('slack_messages.slack_channel_id', 'slack_channels.id')
                ->whereNull('deleted_at')
                ->orderByDesc('ts')
                ->limit(1)])
            ->get();
        if ($channels->isEmpty()) {
            return [];
        }
        $ids     = $channels->pluck('id')->all();
        $reads   = GetSlackChannelUnreadCounts::make()->handle($user, $ids);
        $unseen  = array_values(array_diff($ids, array_keys($reads)));
        if ($unseen !== []) {
            MarkSlackChannelRead::make()->upToNow($user, $unseen, onlyMissing: true);
            $reads += GetSlackChannelUnreadCounts::make()->handle($user, $unseen);
        }

        return $channels
            ->sortBy([['last_message_at', 'desc'], ['name', 'asc']])
            ->map(fn (SlackChannel $channel) => [
                'id'              => $channel->id,
                'slack_id'        => $channel->slack_id,
                'name'            => $direct ? self::directName($channel, $user) : $channel->name,
                'topic'           => ($channel->data['topic']['value'] ?? null) ?: ($channel->data['purpose']['value'] ?? null) ?: null,
                'is_archived'     => $channel->is_archived,
                'workspace'       => $channel->slack_team_id ? SlackWorkspace::forTeam($channel->slack_team_id)?->name : null,
                'in_list'         => !$public || (bool) $channel->in_list,
                'last_message_at' => $channel->last_message_at ? Carbon::parse($channel->last_message_at)->toIso8601String() : null,
                'unread_count'    => $reads[$channel->id]['count'] ?? 0,
                'last_read_ts'    => $reads[$channel->id]['last_read_ts'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Public channels that belong in the person's V·Desks list: not hidden by a Verba admin, and shown to everyone or to
     * one of the person's organisations (INI-595). The page still gets every channel the person may read, flagged
     * in_list, so links, jump and search reach the rest; the sidebar also keeps starred, open and unread ones.
     */
    private function inListQuery(User $user): \Illuminate\Database\Query\Builder
    {
        $organisationIds = $user->employees()->pluck('employees.organisation_id')->map(fn ($id) => (int) $id)->all();

        return SlackChannel::query()->select('slack_channels.id')
            ->where('is_hidden_in_lists', false)
            ->when($organisationIds, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereDoesntHave('organisations')
                ->orWhereHas('organisations', fn (Builder $query) => $query->whereIn('organisations.id', $organisationIds))))
            ->toBase();
    }

    /**
     * The channel list and its live channel, computed once and only when a response asks for them. Deferred, they
     * come as one group after the page has opened; a person who is not active cannot read Slack, so they get none.
     *
     * Private channels come in their own list, never in slack_channels, and have no live channel yet: grp.slack.{group}
     * carries public channels only.
     *
     * @return array{slack_channels: mixed, slack_private_channels: mixed, slack_live_channel: mixed, desk_starred: array<int, string>}
     */
    public function listProps(Group $group, User $user, bool $deferred = false): array
    {
        $starred = Arr::get($user->settings, 'desk_starred') ?? [];

        if (!$user->status) {
            return ['slack_channels' => [], 'slack_private_channels' => [], 'slack_listed_channels' => [], 'slack_direct_channels' => [], 'slack_live_channel' => null, 'slack_live_team_channels' => [], 'desk_starred' => $starred, 'slack_private_opt_in' => null];
        }

        $memo        = null;
        $channels    = function () use (&$memo, $user): array {
            return $memo ??= $this->channels($user);
        };
        $liveChannel = fn () => collect($channels())->contains(fn (array $channel) => $channel['workspace'] === null) ? 'grp.slack.'.$group->id : null;
        $teamLive    = fn (): array => SlackChannel::visibleTo($user)->whereNotNull('slack_team_id')->distinct()->pluck('slack_team_id')
            ->map(fn (string $teamId) => 'grp.slack.'.$group->id.'.team.'.$teamId)->values()->all();
        $private     = fn (): array => $this->channels($user, private: true);
        $direct      = fn (): array => $this->channels($user, direct: true);
        $listed      = fn (): array => $this->listedChannels($user);

        return $deferred
            ? ['slack_private_opt_in' => Inertia::defer(fn () => $this->privateOptIn($user), 'slack'), 'slack_channels' => Inertia::defer($channels, 'slack'), 'slack_private_channels' => Inertia::defer($private, 'slack'), 'slack_listed_channels' => Inertia::defer($listed, 'slack'), 'slack_direct_channels' => Inertia::defer($direct, 'slack'), 'slack_live_channel' => Inertia::defer($liveChannel, 'slack'), 'slack_live_team_channels' => Inertia::defer($teamLive, 'slack'), 'desk_starred' => $starred]
            : ['slack_private_opt_in' => $this->privateOptIn($user), 'slack_channels' => $channels, 'slack_private_channels' => $private, 'slack_listed_channels' => $listed, 'slack_direct_channels' => $direct, 'slack_live_channel' => $liveChannel, 'slack_live_team_channels' => $teamLive, 'desk_starred' => $starred];
    }

    /**
     * The private conversations yes (INI-429) offered where it matters, on V·Ds and Verbrations, until the person has given it.
     *
     * @return array{consent_version: string, terms_version: string, start_route: array{name: string}, unavailable_reason: string|null}|null
     */
    public function privateOptIn(User $user): ?array
    {
        if (!SlackWriteAccess::privateOptInOpen() || !SlackWriteAccess::allows($user) || SlackConnection::main()->where('user_id', $user->id)->first()?->hasPrivateConsent()) {
            return null;
        }

        return [
            'consent_version'    => SlackWriteAccess::PRIVATE_CONSENT_VERSION,
            'terms_version'      => SlackWriteAccess::PRIVATE_TERMS_VERSION,
            'start_route'        => ['name' => 'grp.chat.staff.slack.connect.private.start'],
            'unavailable_reason' => StartSlackConnection::unavailableReason($user),
        ];
    }

    /**
     * Private channels the viewer is not in but whose members chose to list them: the name and size only, to ask to join.
     *
     * @return list<array{id: int, name: string|null, member_count: int, requested: bool}>
     */
    public function listedChannels(User $user): array
    {
        return SlackChannel::listedTo($user)
            ->where('is_archived', false)
            ->select(['id', 'name', 'slack_team_id'])
            ->withCount('members')
            ->addSelect(['requested' => DB::table('slack_channel_join_requests')->selectRaw('1')->whereColumn('slack_channel_id', 'slack_channels.id')->where('user_id', $user->id)->limit(1)])
            ->orderBy('name')
            ->get()
            ->map(fn (SlackChannel $channel) => [
                'id'           => $channel->id,
                'name'         => $channel->name,
                'member_count' => $channel->members_count,
                'requested'    => (bool) $channel->requested,
            ])
            ->all();
    }

    private function tsParameter(ActionRequest $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && preg_match('/^\d{1,12}\.\d{1,8}$/', $value) ? $value : null;
    }

    /**
     * A bookmark link is whatever someone typed in Slack: it is linked only when it passes the formatter's check.
     *
     * @return list<array{id: int, title: string, url: string|null, emoji_name: string|null, emoji: array<string, mixed>|null}>
     */
    public function bookmarks(SlackChannel $slackChannel): array
    {
        $bookmarks = $slackChannel->bookmarks()->whereNull('removed_in_slack_at')->orderBy('position')->orderBy('id')
            ->get(['id', 'title', 'link', 'emoji']);
        $emojiName = fn (?string $emoji) => $emoji ? trim($emoji, ':') : null;
        $emoji     = ResolveSlackEmoji::make()->many($bookmarks->map(fn (SlackChannelBookmark $bookmark) => $emojiName($bookmark->emoji))->filter()->unique()->values()->all());

        return $bookmarks->map(fn (SlackChannelBookmark $bookmark) => [
            'id'    => $bookmark->id,
            'title' => $bookmark->title ?: ($bookmark->link ?? ''),
            'url'   => FormatSlackMessages::isSafeUrl($bookmark->link) ? $bookmark->link : null,
            'emoji_name' => $emojiName($bookmark->emoji),
            'emoji' => $emoji[$emojiName($bookmark->emoji) ?? ''] ?? null,
        ])->values()->all();
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title         = __('Verba');
        $slackChannel  = $this->slackChannel;
        $list          = $this->listProps($group, $request->user());

        $breadcrumbs   = ShowStaffMessaging::make()->getBreadcrumbs(null);
        $channelName   = $slackChannel?->direct_type ? self::directName($slackChannel, $request->user()) : $slackChannel?->name;
        if ($slackChannel) {
            $breadcrumbs[] = [
                'type'   => 'simple',
                'simple' => [
                    'route' => ['name' => $slackChannel->direct_type ? 'grp.chat.staff.direct.slack.show' : ($slackChannel->is_private ? 'grp.chat.staff.groups.slack.show' : 'grp.chat.staff.slack.show'), 'parameters' => ['slackChannel' => $slackChannel->id]],
                    'label' => ($slackChannel->is_private ? '' : '#').$channelName,
                ],
            ];
        }

        return Inertia::render(
            'Chat/StaffMessaging',
            [
                'breadcrumbs'    => $breadcrumbs,
                'title'          => $slackChannel ? ($slackChannel->is_private ? '' : '#').$channelName : $title,
                'pageHead'       => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comments'],
                        'title' => $title,
                    ],
                ],
                'selected_ulid'  => null,
                ...$list,
                'slack_channel'      => fn () => $slackChannel ? [
                    'id'           => $slackChannel->id,
                    'name'         => $channelName,
                    'slack_name'   => $slackChannel->getRawOriginal('name'),
                    'is_direct'    => (bool) $slackChannel->direct_type,
                    'topic'        => ($slackChannel->data['topic']['value'] ?? null) ?: null,
                    'purpose'      => ($slackChannel->data['purpose']['value'] ?? null) ?: null,
                    'is_archived'  => $slackChannel->is_archived,
                    'is_private'   => $slackChannel->is_private,
                    'live_channel' => $slackChannel->is_public ? \App\Events\SlackChannelMessageChanged::channelName($slackChannel) : null,
                    'is_listed'    => $slackChannel->is_private && !$slackChannel->direct_type ? $slackChannel->is_listed : null,
                    'brought_by'   => $slackChannel->is_private && $slackChannel->brought_by_user_id ? User::whereKey($slackChannel->brought_by_user_id)->first()?->chatName() : null,
                    'member_count' => $slackChannel->members()->count(),
                    'is_mine'      => $slackChannel->is_public && !$slackChannel->direct_type ? $slackChannel->members()->whereIn('slack_user_id', SlackChannel::linkedSlackIds($request->user()))->exists() : null,
                    'pinned_count' => $slackChannel->messages()->whereNotNull('pinned_at')->count(),
                    'bookmarks'    => $this->bookmarks($slackChannel),
                    'last_read_ts' => DB::table('slack_channel_reads')->where('user_id', $request->user()->id)->where('slack_channel_id', $slackChannel->id)->value('last_read_ts'),
                    'write'        => $slackChannel->direct_type ? $this->directWriteState($request->user(), $slackChannel) : ($slackChannel->is_private ? null : $this->writeState($request->user(), $slackChannel)),
                    'wallpaper'        => StaffChatWallpaper::forSlackChannel($request->user(), $slackChannel->id),
                    'wallpaper_is_set' => (bool) Arr::get($request->user()->settings, "chat_wallpaper_channels.$slackChannel->id"),
                ] : null,
                'slack_thread_ts' => $slackChannel ? $this->tsParameter($request, 'thread') : null,
                'slack_around_ts' => $slackChannel ? $this->tsParameter($request, 'around') : null,
                'slack_reply_ts'  => $slackChannel ? $this->tsParameter($request, 'reply') : null,
                'slack_messages'  => $slackChannel
                    ? Inertia::scroll(fn () => GetSlackChannelMessages::make()->handle($slackChannel, aroundTs: $this->tsParameter($request, 'around')))->defer()
                    : null,
            ]
        );
    }

    /**
     * A Slack DM or group DM is written to only with the person's own token and their private opt-in (INI-593), never by
     * the stand-in bot; without them the box gives the reason. Messages, edits, deletes, reactions and files; no pins or
     * bookmarks (direct).
     *
     * @return array{allowed: bool, reason: string|null, stand_in: bool, direct: bool, connect_url: string|null, people?: list<array{id: string, label: string}>, channels?: list<array{id: string, label: string}>, member_count?: int, max_file_bytes?: int, max_large_file_bytes?: int|null}
     */
    public function directWriteState(User $user, SlackChannel $slackChannel): array
    {
        $allowed = true;
        $reason  = self::isClosedDirect($slackChannel) ? __('This conversation is closed in Slack, the other person is no longer there.') : SlackWriteAccess::directReasonUnavailable($user);
        $state   = [
            'allowed'     => $allowed,
            'reason'      => $reason,
            'stand_in'    => false,
            'direct'      => true,
            'connect_url' => $reason && !self::isClosedDirect($slackChannel) && app()->environment(SlackWriteAccess::WRITE_ENVIRONMENTS) ? route('grp.profile.edit', ['section' => 'slack']) : null,
        ];
        if (!$allowed || $reason) {
            return $state;
        }

        $members = $slackChannel->members()->pluck('slack_user_id');

        return [
            ...$state,
            'people'       => SlackUser::whereIn('slack_id', $members)->where('is_bot', false)->where('is_deleted', false)->get(['slack_id', 'display_name', 'real_name'])
                ->map(fn (SlackUser $person) => ['id' => $person->slack_id, 'label' => $person->display_name ?: $person->real_name ?: $person->slack_id])
                ->sortBy('label')->values()->all(),
            'channels'     => [],
            'member_count' => $members->count(),
            'max_file_bytes' => \App\Actions\Chat\Staff\Slack\Write\UploadSlackFile::maxBytes(),
            'max_large_file_bytes' => \App\Actions\Chat\Staff\Slack\Write\UploadSlackLargeFile::maxBytes(),
        ];
    }

    public static function isClosedDirect(SlackChannel $slackChannel): bool
    {
        return $slackChannel->is_archived || ($slackChannel->direct_type === 'im'
            && SlackUser::whereIn('slack_id', $slackChannel->members()->select('slack_user_id'))->where('is_deleted', true)->exists());
    }

    /**
     * Whether the composer shows, and when it cannot be used yet, the sentence saying why and the way to connect.
     *
     * When the person can write, also who and which channels the box may mention: live people, members of this channel first.
     *
     * @return array{allowed: bool, reason: string|null, stand_in: bool, connect_url: string|null, people?: list<array{id: string, label: string}>, channels?: list<array{id: string, label: string}>, member_count?: int, max_file_bytes?: int, max_large_file_bytes?: int|null}
     */
    public function writeState(User $user, ?SlackChannel $slackChannel = null): array
    {
        $workspace = $slackChannel ? \App\Actions\Chat\Staff\Slack\SlackWorkspace::forChannel($slackChannel) : \App\Actions\Chat\Staff\Slack\SlackWorkspace::main();
        $allowed   = SlackWriteAccess::allows($user) && $workspace !== null;
        $reason    = $allowed ? SlackWriteAccess::reasonUnavailable($user, $workspace) : null;
        $standIn   = $reason && SlackWriteClient::standInFor($user, $workspace) !== null;
        $state     = [
            'allowed'     => $allowed,
            'reason'      => $reason,
            'stand_in'    => $standIn,
            'connect_url' => $reason && app()->environment(SlackWriteAccess::WRITE_ENVIRONMENTS) && $workspace->personOf($user) ? route('grp.profile.edit', ['section' => 'slack']) : null,
        ];
        if (!$allowed || ($reason && !$standIn) || !$slackChannel) {
            return $state;
        }

        $members = $slackChannel->members()->pluck('slack_user_id')->flip();
        $people  = SlackUser::where('is_bot', false)->where('is_deleted', false)->get(['slack_id', 'display_name', 'real_name'])
            ->map(fn (SlackUser $person) => ['id' => $person->slack_id, 'label' => $person->display_name ?: $person->real_name ?: $person->slack_id, 'member' => $members->has($person->slack_id)])
            ->sortBy([['member', 'desc'], ['label', 'asc']])
            ->map(fn (array $person) => ['id' => $person['id'], 'label' => $person['label']])
            ->values()->all();

        return [
            ...$state,
            'people'       => [['id' => '!here', 'label' => 'here'], ['id' => '!channel', 'label' => 'channel'], ...$people],
            'channels'     => SlackChannel::visibleTo($user)->orderBy('name')->get(['slack_id', 'name', 'slack_team_id'])->map(fn (SlackChannel $channel) => ['id' => $channel->slack_id, 'label' => (string) $channel->name])->all(),
            'member_count' => $members->count(),
            'max_file_bytes' => \App\Actions\Chat\Staff\Slack\Write\UploadSlackFile::maxBytes(),
            'max_large_file_bytes' => \App\Actions\Chat\Staff\Slack\Write\UploadSlackLargeFile::maxBytes(),
        ];
    }
}
