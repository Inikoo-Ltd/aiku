<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackMessageVersion;
use App\Models\Chat\SlackMessageReaction;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    public const array COLUMNS = ['id', 'slack_channel_id', 'ts', 'thread_ts', 'slack_user_id', 'user_id', 'subtype', 'text', 'sent_at', 'edited_at', 'pinned_at', 'data', 'deleted_at'];

    public const int REPLIERS_SHOWN = 3;

    /**
     * @return array<string, \Closure|string>
     */
    public static function relations(): array
    {
        return [
            'slackUser:id,slack_id,user_id,name,real_name,display_name,is_bot,is_deleted',
            'reactions' => fn ($query) => $query->select(['id', 'slack_message_id', 'name', 'slack_user_id'])->orderBy('id'),
        ];
    }

    /**
     * The main stream: top-level messages, thread parents and replies also sent to the channel. A deleted parent stays
     * while its thread has live replies, so the thread can still be opened.
     */
    public static function streamQuery(SlackChannel $slackChannel): Builder
    {
        return SlackMessage::withTrashed()
            ->where('slack_channel_id', $slackChannel->id)
            ->where(function ($query) {
                $query->whereNull('thread_ts')->orWhereColumn('thread_ts', 'ts')->orWhere('subtype', 'thread_broadcast');
            })
            ->where(function ($query) {
                $query->whereNull('deleted_at')->orWhere(function ($query) {
                    $query->whereColumn('thread_ts', 'ts')->whereExists(fn ($replies) => $replies->from('slack_messages as replies')
                        ->whereColumn('replies.slack_channel_id', 'slack_messages.slack_channel_id')
                        ->whereColumn('replies.thread_ts', 'slack_messages.ts')
                        ->whereColumn('replies.ts', '!=', 'slack_messages.ts')
                        ->whereNull('replies.deleted_at'));
                });
            });
    }

    /**
     * $aroundTs opens the stream with that message near the bottom, a few newer ones under it, for "jump to message".
     */
    public function handle(SlackChannel $slackChannel, int $perPage = self::PER_PAGE, ?string $aroundTs = null): CursorPaginator
    {
        $cursor = null;
        if ($aroundTs !== null && !request()->has(self::CURSOR_NAME)) {
            $newer  = self::streamQuery($slackChannel)->where('ts', '>', $aroundTs)->orderBy('ts')->offset(min(9, $perPage - 1))->limit(1)->value('ts');
            $cursor = $newer ? new Cursor(['ts' => $newer]) : null;
        }

        $page = self::streamQuery($slackChannel)
            ->with(self::relations())
            ->orderByDesc('ts')
            ->cursorPaginate($perPage, self::COLUMNS, self::CURSOR_NAME, $cursor);

        return $page->setCollection(collect($this->rows($slackChannel, $page->getCollection())));
    }

    /**
     * Shapes messages for the screen in a fixed number of queries: reactions, the people who reacted or replied,
     * reply counts from our own rows (Slack's reply_count goes stale), and the formatter's names and emoji. Messages
     * must be loaded with relations() and COLUMNS.
     *
     * @param  Collection<int, SlackMessage>  $messages
     * @return list<array<string, mixed>>
     */
    public function rows(SlackChannel $slackChannel, Collection $messages, ?User $viewer = null): array
    {
        $viewer ??= request()->user();
        $live          = $messages->filter(fn (SlackMessage $message) => $message->deleted_at === null);
        $reactionNames = $live->flatMap(fn (SlackMessage $message) => $message->reactions->pluck('name'))->unique()->values()->all();
        $formatter     = FormatSlackMessages::make();
        $nodes         = $formatter->handle($live->values(), $reactionNames);

        $threads = $this->threadStats($slackChannel, $messages);
        $files   = GetSlackMessageFiles::make()->handle($live, $slackChannel->is_private);

        $editedIds  = $live->whereNotNull('edited_at')->pluck('id')->all();
        $withVersions = $editedIds === [] ? [] : array_flip(SlackMessageVersion::whereIn('slack_message_id', $editedIds)->distinct()->pluck('slack_message_id')->all());

        $peopleIds = $live->flatMap(fn (SlackMessage $message) => $message->reactions->pluck('slack_user_id'))
            ->merge(collect($threads)->flatMap(fn (array $thread) => $thread['repliers']))
            ->unique()->values()->all();
        $people = $peopleIds === [] ? collect() : SlackUser::whereIn('slack_id', $peopleIds)
            ->get(['slack_id', 'user_id', 'name', 'real_name', 'display_name', 'is_bot'])
            ->keyBy('slack_id');

        $userIds = $live->map(fn (SlackMessage $message) => $this->linkedUserId($message))
            ->merge($people->where('is_bot', false)->pluck('user_id'))
            ->filter()->unique()->values()->all();
        $users = $userIds === [] ? collect() : User::whereIn('id', $userIds)
            ->with('image')
            ->get(['id', 'username', 'nickname', 'contact_name', 'image_id'])
            ->keyBy('id');

        $personUser = fn (SlackUser $slackUser) => !$slackUser->is_bot && $slackUser->user_id ? $users->get($slackUser->user_id) : null;
        $reactors   = $people->map(fn (SlackUser $slackUser) => $personUser($slackUser)?->chatName() ?? $this->personName($slackUser));
        $mySlackIds = $viewer ? $people->filter(fn (SlackUser $slackUser) => $slackUser->user_id === $viewer->id)->keys()->all() : [];

        return $messages->map(function (SlackMessage $message) use ($nodes, $formatter, $reactors, $users, $threads, $people, $personUser, $mySlackIds, $files, $withVersions, $slackChannel, $viewer) {
            $isParent = $message->thread_ts === $message->ts;
            $thread   = $isParent ? ($threads[$message->ts] ?? null) : null;
            $common   = [
                'id'              => $message->id,
                'ts'              => $message->ts,
                'thread_ts'       => $message->thread_ts,
                'is_broadcast'    => $message->subtype === 'thread_broadcast',
                'is_deleted'      => $message->deleted_at !== null,
                'sent_at'         => $message->sent_at?->toIso8601String(),
                'reply_count'     => $thread['count'] ?? 0,
                'latest_reply_at' => $thread ? $this->tsToIso($thread['latest']) : null,
                'reply_users'     => collect($thread['repliers'] ?? [])->map(function (string $slackId) use ($people, $personUser) {
                    $slackUser = $people->get($slackId);
                    $user      = $slackUser ? $personUser($slackUser) : null;

                    return [
                        'name'   => $user?->chatName() ?? ($slackUser ? $this->personName($slackUser) : null),
                        'avatar' => $user?->image_id ? User::chatAvatarOf($user->imageSources(0, 48)) : null,
                    ];
                })->values()->all(),
            ];

            if ($message->deleted_at !== null) {
                return array_merge($common, [
                    'is_system'    => false,
                    'author'       => ['key' => null, 'user_id' => null, 'name' => null, 'avatar' => null, 'is_bot' => false],
                    'is_edited'    => false,
                    'versions_url' => null,
                    'is_pinned'    => false,
                    'nodes'        => [],
                    'reactions'    => [],
                    'my_reactions' => [],
                    'files'        => [],
                ]);
            }

            $isViewers = $viewer && $this->linkedUserId($message) === $viewer->id;

            return array_merge($common, $isViewers ? ['text' => self::editableText($message->text)] : [], [
                'is_system'    => in_array($message->subtype, self::SYSTEM_SUBTYPES, true),
                'is_gap'       => $slackChannel->direct_type !== null && $message->data === null,
                'author'       => $this->author($message, $users->get($this->linkedUserId($message) ?? 0)),
                'is_edited'    => $message->edited_at !== null,
                'versions_url' => isset($withVersions[$message->id]) ? route('grp.chat.staff.slack.versions', ['slackChannel' => $slackChannel->id, 'ts' => $message->ts]) : null,
                'is_pinned'    => $message->pinned_at !== null,
                'nodes'        => $nodes[$message->id] ?? [],
                'reactions'    => $this->reactions($message, $formatter->resolvedEmoji, $reactors->all()),
                'my_reactions' => $message->reactions->whereIn('slack_user_id', $mySlackIds)->pluck('name')->unique()->values()->all(),
                'files'        => $files[$message->id] ?? [],
            ]);
        })->values()->all();
    }

    /**
     * Reply count, last reply and the latest repliers of every thread parent among $messages, in one grouped query.
     *
     * @param  Collection<int, SlackMessage>  $messages
     * @return array<string, array{count: int, latest: string, repliers: list<string>}>
     */
    private function threadStats(SlackChannel $slackChannel, Collection $messages): array
    {
        $parents = $messages->filter(fn (SlackMessage $message) => $message->thread_ts === $message->ts)->pluck('ts')->values()->all();
        if ($parents === []) {
            return [];
        }

        return SlackMessage::where('slack_channel_id', $slackChannel->id)
            ->whereIn('thread_ts', $parents)
            ->whereColumn('ts', '!=', 'thread_ts')
            ->groupBy('thread_ts')
            ->selectRaw('thread_ts, count(*) as replies, max(ts) as latest, array_to_json(array_agg(slack_user_id order by ts desc)) as repliers')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row) => [$row->thread_ts => [
                'count'    => (int) $row->replies,
                'latest'   => $row->latest,
                'repliers' => array_slice(array_values(array_unique(array_filter(json_decode($row->repliers, true) ?? []))), 0, self::REPLIERS_SHOWN),
            ]])
            ->all();
    }

    /**
     * A bot wrote it: Slack's bot_message, no person as author, or an author that is a bot. A message a person sends
     * through an app (aiku included) carries the app's bot_id beside the person's user and is still the person's.
     */
    public static function isBotMessage(SlackMessage $message): bool
    {
        return $message->subtype === 'bot_message' || !$message->slack_user_id || (bool) $message->slackUser?->is_bot;
    }

    /**
     * The edit box's text: Slack's own, entities and mentions as they are, without the names of linked channels,
     * which the formatter deliberately leaves out.
     */
    public static function editableText(?string $text): string
    {
        return (string) preg_replace('/<#(C[A-Z0-9]+)\|[^>]*>/', '<#$1>', (string) $text);
    }

    /**
     * The aiku user behind a human Slack author: the message's own user_id, else the Slack person's link.
     */
    private function linkedUserId(SlackMessage $message): ?int
    {
        if (self::isBotMessage($message)) {
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
        $isBot      = self::isBotMessage($message);

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
