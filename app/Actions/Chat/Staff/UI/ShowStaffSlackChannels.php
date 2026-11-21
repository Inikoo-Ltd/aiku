<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\UI;

use App\Actions\Chat\Staff\Slack\FormatSlackMessages;
use App\Actions\Chat\Staff\Slack\GetSlackChannelMessages;
use App\Actions\Chat\Staff\Slack\ResolveSlackEmoji;
use App\Actions\OrgAction;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackChannelBookmark;
use App\Models\Chat\SlackMessage;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
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
        abort_unless($slackChannel->isVisibleTo($request->user()), 403);

        $this->slackChannel = $slackChannel;
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    /**
     * @return list<array{id: int, slack_id: string, name: string|null, topic: string|null, is_archived: bool, last_message_at: string|null}>
     */
    public function channels(User $user): array
    {
        return SlackChannel::visibleTo($user)
            ->select(['id', 'slack_id', 'name', 'is_archived', 'data'])
            ->addSelect(['last_message_at' => SlackMessage::select('sent_at')
                ->whereColumn('slack_messages.slack_channel_id', 'slack_channels.id')
                ->whereNull('deleted_at')
                ->orderByDesc('ts')
                ->limit(1)])
            ->get()
            ->sortBy([['last_message_at', 'desc'], ['name', 'asc']])
            ->map(fn (SlackChannel $channel) => [
                'id'              => $channel->id,
                'slack_id'        => $channel->slack_id,
                'name'            => $channel->name,
                'topic'           => ($channel->data['topic']['value'] ?? null) ?: ($channel->data['purpose']['value'] ?? null) ?: null,
                'is_archived'     => $channel->is_archived,
                'last_message_at' => $channel->last_message_at ? Carbon::parse($channel->last_message_at)->toIso8601String() : null,
            ])
            ->values()
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
     * @return list<array{id: int, title: string, url: string|null, emoji: array<string, mixed>|null}>
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
            'emoji' => $emoji[$emojiName($bookmark->emoji) ?? ''] ?? null,
        ])->values()->all();
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title        = __('Internal Messages');
        $slackChannel = $this->slackChannel;

        $breadcrumbs   = ShowStaffMessaging::make()->getBreadcrumbs(null);
        $breadcrumbs[] = [
            'type'   => 'simple',
            'simple' => [
                'route' => ['name' => 'grp.chat.staff.slack.index'],
                'label' => __('Slack'),
            ],
        ];
        if ($slackChannel) {
            $breadcrumbs[] = [
                'type'   => 'simple',
                'simple' => [
                    'route' => ['name' => 'grp.chat.staff.slack.show', 'parameters' => ['slackChannel' => $slackChannel->id]],
                    'label' => '#'.$slackChannel->name,
                ],
            ];
        }

        return Inertia::render(
            'Chat/StaffMessaging',
            [
                'breadcrumbs'    => $breadcrumbs,
                'title'          => $slackChannel ? '#'.$slackChannel->name : $title,
                'pageHead'       => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comments'],
                        'title' => $title,
                    ],
                ],
                'selected_ulid'  => null,
                'slack_channels' => $this->channels($request->user()),
                'slack_channel'  => $slackChannel ? [
                    'id'           => $slackChannel->id,
                    'name'         => $slackChannel->name,
                    'topic'        => ($slackChannel->data['topic']['value'] ?? null) ?: null,
                    'purpose'      => ($slackChannel->data['purpose']['value'] ?? null) ?: null,
                    'is_archived'  => $slackChannel->is_archived,
                    'member_count' => $slackChannel->members()->count(),
                    'pinned_count' => $slackChannel->messages()->whereNotNull('pinned_at')->count(),
                    'bookmarks'    => $this->bookmarks($slackChannel),
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
}
