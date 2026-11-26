<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\UI;

use App\Actions\Chat\Staff\Slack\FormatSlackMessages;
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
     * Each channel with this person's unread count and read position; a channel seen for the first time starts read.
     *
     * @return list<array{id: int, slack_id: string, name: string|null, topic: string|null, is_archived: bool, last_message_at: string|null, unread_count: int, last_read_ts: string|null}>
     */
    public function channels(User $user, bool $private = false): array
    {
        $channels = ($private ? SlackChannel::privateVisibleTo($user) : SlackChannel::visibleTo($user))
            ->select(['id', 'slack_id', 'name', 'is_archived', 'data'])
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
                'name'            => $channel->name,
                'topic'           => ($channel->data['topic']['value'] ?? null) ?: ($channel->data['purpose']['value'] ?? null) ?: null,
                'is_archived'     => $channel->is_archived,
                'last_message_at' => $channel->last_message_at ? Carbon::parse($channel->last_message_at)->toIso8601String() : null,
                'unread_count'    => $reads[$channel->id]['count'] ?? 0,
                'last_read_ts'    => $reads[$channel->id]['last_read_ts'] ?? null,
            ])
            ->values()
            ->all();
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
            return ['slack_channels' => [], 'slack_private_channels' => [], 'slack_live_channel' => null, 'desk_starred' => $starred];
        }

        $memo        = null;
        $channels    = function () use (&$memo, $user): array {
            return $memo ??= $this->channels($user);
        };
        $liveChannel = fn () => $channels() === [] ? null : 'grp.slack.'.$group->id;
        $private     = fn (): array => $this->channels($user, private: true);

        return $deferred
            ? ['slack_channels' => Inertia::defer($channels, 'slack'), 'slack_private_channels' => Inertia::defer($private, 'slack'), 'slack_live_channel' => Inertia::defer($liveChannel, 'slack'), 'desk_starred' => $starred]
            : ['slack_channels' => $channels, 'slack_private_channels' => $private, 'slack_live_channel' => $liveChannel, 'desk_starred' => $starred];
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
        if ($slackChannel) {
            $breadcrumbs[] = [
                'type'   => 'simple',
                'simple' => [
                    'route' => ['name' => $slackChannel->is_private ? 'grp.chat.staff.groups.slack.show' : 'grp.chat.staff.slack.show', 'parameters' => ['slackChannel' => $slackChannel->id]],
                    'label' => ($slackChannel->is_private ? '' : '#').$slackChannel->name,
                ],
            ];
        }

        return Inertia::render(
            'Chat/StaffMessaging',
            [
                'breadcrumbs'    => $breadcrumbs,
                'title'          => $slackChannel ? ($slackChannel->is_private ? '' : '#').$slackChannel->name : $title,
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
                    'name'         => $slackChannel->name,
                    'topic'        => ($slackChannel->data['topic']['value'] ?? null) ?: null,
                    'purpose'      => ($slackChannel->data['purpose']['value'] ?? null) ?: null,
                    'is_archived'  => $slackChannel->is_archived,
                    'is_private'   => $slackChannel->is_private,
                    'member_count' => $slackChannel->members()->count(),
                    'pinned_count' => $slackChannel->messages()->whereNotNull('pinned_at')->count(),
                    'bookmarks'    => $this->bookmarks($slackChannel),
                    'last_read_ts' => DB::table('slack_channel_reads')->where('user_id', $request->user()->id)->where('slack_channel_id', $slackChannel->id)->value('last_read_ts'),
                    'write'        => $slackChannel->is_private ? null : $this->writeState($request->user(), $slackChannel),
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
     * Whether the composer shows, and when it cannot be used yet, the sentence saying why and the way to connect.
     *
     * When the person can write, also who and which channels the box may mention: live people, members of this channel first.
     *
     * @return array{allowed: bool, reason: string|null, stand_in: bool, connect_url: string|null, people?: list<array{id: string, label: string}>, channels?: list<array{id: string, label: string}>, member_count?: int, max_file_bytes?: int}
     */
    public function writeState(User $user, ?SlackChannel $slackChannel = null): array
    {
        $allowed = SlackWriteAccess::allows($user);
        $reason  = $allowed ? SlackWriteAccess::reasonUnavailable($user) : null;
        $standIn = $reason && SlackWriteClient::standInFor($user) !== null;
        $state   = [
            'allowed'     => $allowed,
            'reason'      => $reason,
            'stand_in'    => $standIn,
            'connect_url' => $reason && app()->environment(SlackWriteAccess::WRITE_ENVIRONMENTS) ? route('grp.profile.edit', ['section' => 'slack']) : null,
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
            'channels'     => SlackChannel::visibleTo($user)->orderBy('name')->get(['slack_id', 'name'])->map(fn (SlackChannel $channel) => ['id' => $channel->slack_id, 'label' => (string) $channel->name])->all(),
            'member_count' => $members->count(),
            'max_file_bytes' => \App\Actions\Chat\Staff\Slack\Write\UploadSlackFile::maxBytes(),
        ];
    }
}
