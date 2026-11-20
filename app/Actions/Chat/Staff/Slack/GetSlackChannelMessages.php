<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackMessageReaction;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * One page of a Slack channel's main stream, newest first, keyed by ts on the (slack_channel_id, ts) index. Thread
 * replies stay out unless also sent to the channel. A page costs a fixed number of queries, whatever its size:
 * messages, reactions, the people who reacted, and the formatter's names and emoji.
 */
class GetSlackChannelMessages
{
    use AsObject;

    public const int PER_PAGE = 50;

    public const string CURSOR_NAME = 'cursor';

    public const array SYSTEM_SUBTYPES = [
        'channel_join', 'channel_leave', 'channel_topic', 'channel_purpose', 'channel_name', 'channel_archive',
        'channel_unarchive', 'channel_convert_to_public', 'group_join', 'group_leave', 'bot_add', 'bot_remove',
        'pinned_item', 'unpinned_item',
    ];

    public function handle(SlackChannel $slackChannel, int $perPage = self::PER_PAGE): CursorPaginator
    {
        $page = SlackMessage::where('slack_channel_id', $slackChannel->id)
            ->where(function ($query) {
                $query->whereNull('thread_ts')->orWhereColumn('thread_ts', 'ts')->orWhere('subtype', 'thread_broadcast');
            })
            ->with([
                'slackUser:id,slack_id,user_id,name,real_name,display_name,is_bot,is_deleted',
                'reactions:id,slack_message_id,name,slack_user_id',
            ])
            ->orderByDesc('ts')
            ->cursorPaginate($perPage, ['id', 'slack_channel_id', 'ts', 'thread_ts', 'slack_user_id', 'user_id', 'subtype', 'text', 'sent_at', 'edited_at', 'pinned_at', 'data'], self::CURSOR_NAME);

        $messages      = $page->getCollection();
        $reactionNames = $messages->flatMap(fn (SlackMessage $message) => $message->reactions->pluck('name'))->unique()->values()->all();
        $formatter     = FormatSlackMessages::make();
        $nodes         = $formatter->handle($messages, $reactionNames);

        $reactorIds = $messages->flatMap(fn (SlackMessage $message) => $message->reactions->pluck('slack_user_id'))->unique()->values()->all();
        $reactorPeople = $reactorIds === [] ? collect() : SlackUser::whereIn('slack_id', $reactorIds)
            ->get(['slack_id', 'user_id', 'name', 'real_name', 'display_name', 'is_bot']);

        $userIds = $messages->map(fn (SlackMessage $message) => $this->linkedUserId($message))
            ->merge($reactorPeople->where('is_bot', false)->pluck('user_id'))
            ->filter()->unique()->values()->all();
        $users = $userIds === [] ? collect() : User::whereIn('id', $userIds)
            ->with('image')
            ->get(['id', 'username', 'nickname', 'contact_name', 'image_id'])
            ->keyBy('id');

        $reactors = $reactorPeople->mapWithKeys(fn (SlackUser $slackUser) => [
            $slackUser->slack_id => (!$slackUser->is_bot && $slackUser->user_id ? $users->get($slackUser->user_id)?->chatName() : null) ?? $this->personName($slackUser),
        ]);

        return $page->setCollection($messages->map(fn (SlackMessage $message) => [
            'id'              => $message->id,
            'ts'              => $message->ts,
            'is_system'       => in_array($message->subtype, self::SYSTEM_SUBTYPES, true),
            'author'          => $this->author($message, $users->get($this->linkedUserId($message) ?? 0)),
            'sent_at'         => $message->sent_at?->toIso8601String(),
            'is_edited'       => $message->edited_at !== null,
            'is_pinned'       => $message->pinned_at !== null,
            'nodes'           => $nodes[$message->id] ?? [],
            'reactions'       => $this->reactions($message, $formatter->resolvedEmoji, $reactors->all()),
            'reply_count'     => $message->thread_ts === $message->ts ? (int) ($message->data['reply_count'] ?? 0) : 0,
            'latest_reply_at' => $message->thread_ts === $message->ts ? $this->tsToIso($message->data['latest_reply'] ?? null) : null,
            'files'           => $this->files($message->data ?? []),
        ]));
    }

    /**
     * The aiku user behind a human Slack author: the message's own user_id, else the Slack person's link.
     */
    private function linkedUserId(SlackMessage $message): ?int
    {
        if ($message->slackUser?->is_bot || isset($message->data['bot_id'])) {
            return null;
        }

        return $message->user_id ?? $message->slackUser?->user_id;
    }

    /**
     * A linked author looks as in the internal staff chat (chatName, chatAvatarOf); anyone else gets the Slack name
     * and no picture. Nothing from Slack's image servers is ever sent.
     *
     * @return array{key: string|null, user_id: int|null, name: string|null, avatar: array<string, mixed>|null, is_bot: bool}
     */
    private function author(SlackMessage $message, ?User $user): array
    {
        $data       = $message->data ?? [];
        $slackUser  = $message->slackUser;
        $botProfile = is_array($data['bot_profile'] ?? null) ? $data['bot_profile'] : [];
        $isBot      = ($slackUser?->is_bot ?? false) || isset($data['bot_id']);

        $name = $slackUser ? $this->personName($slackUser) : null;
        if ($isBot) {
            $name = $this->stringOrNull($data['username'] ?? null) ?? $this->stringOrNull($botProfile['name'] ?? null) ?? $name;
        }

        return [
            'key'     => $user ? 'user-'.$user->id : ($message->slack_user_id ?? $this->stringOrNull($data['bot_id'] ?? null)),
            'user_id' => $user?->id,
            'name'    => $user?->chatName() ?? $name,
            'avatar'  => $user?->image_id ? User::chatAvatarOf($user->imageSources(0, 48)) : null,
            'is_bot'  => $isBot,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>|null>  $resolvedEmoji
     * @param  array<string, string|null>  $reactors
     * @return list<array{name: string, unicode: string|null, url: string|null, count: int, people: list<string>}>
     */
    private function reactions(SlackMessage $message, array $resolvedEmoji, array $reactors): array
    {
        return $message->reactions
            ->groupBy('name')
            ->map(fn ($group, string $name) => [
                'name'    => $name,
                'unicode' => $resolvedEmoji[$name]['unicode'] ?? null,
                'url'     => $resolvedEmoji[$name]['url'] ?? null,
                'count'   => $group->count(),
                'people'  => $group->map(fn (SlackMessageReaction $reaction) => $reactors[$reaction->slack_user_id] ?? null)->filter()->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{name: string}>
     */
    private function files(array $data): array
    {
        return collect(is_array($data['files'] ?? null) ? $data['files'] : [])
            ->filter(fn ($file) => is_array($file))
            ->map(fn (array $file) => ['name' => $this->stringOrNull($file['name'] ?? null) ?? $this->stringOrNull($file['title'] ?? null) ?? __('file')])
            ->values()
            ->all();
    }

    private function personName(SlackUser $slackUser): ?string
    {
        return $slackUser->display_name ?: $slackUser->real_name ?: $slackUser->name;
    }

    private function tsToIso(mixed $ts): ?string
    {
        return is_string($ts) && is_numeric($ts) ? Carbon::createFromTimestamp((float) $ts)->toIso8601String() : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
