<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Json;

use App\Actions\Chat\Staff\Slack\FormatSlackMessages;
use App\Actions\Chat\Staff\Slack\GetSlackChannelMessages;
use App\Actions\Chat\Staff\Slack\RefreshSlackMessageSearch;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Searches the stored Slack messages of every channel the user may see (SlackChannel::visibleTo, inside the query),
 * newest first, paged by key. The words go through websearch_to_tsquery, so quotes, "or" and -word work and nothing
 * typed can break the query; every word also matches as the start of a longer one ("supp" finds "supplier",
 * "AWD1973" finds "AWD197367"), but never from the middle ("197367" does not find "AWD197367").
 */
class SearchSlackMessages
{
    use AsAction;

    public const int PER_PAGE = 20;

    public const int MIN_CHARACTERS = 2;

    private const int SNIPPET_BYTES = 320;

    private const string PREFIX_QUERY = <<<'SQL'
        search_vector @@ regexp_replace(websearch_to_tsquery('simple', extensions.remove_accents(?))::text, '(''(?:[^'']|'''')+'')', '\1:*', 'g')::tsquery
        SQL;

    public function rules(): array
    {
        return [
            'q'        => ['required', 'string', 'max:200'],
            'channel'  => ['sometimes', 'nullable', 'integer'],
            'person'   => ['sometimes', 'nullable', 'string', 'max:64'],
            'from'     => ['sometimes', 'nullable', 'date'],
            'to'       => ['sometimes', 'nullable', 'date'],
            'has_file' => ['sometimes', 'nullable', 'boolean'],
            'cursor'   => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @param  array{q: string, channel?: int|null, person?: string|null, from?: string|null, to?: string|null, has_file?: bool|null}  $filters
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, too_short: bool}
     */
    public function handle(User $user, array $filters, int $perPage = self::PER_PAGE): array
    {
        $search = RefreshSlackMessageSearch::clean(trim($filters['q']));
        if (!str_contains($search, '"')) {
            $words  = fn (string $term) => preg_match_all('/[\p{L}\p{N}]/u', $term);
            $terms  = array_filter(preg_split('/\s+/u', $search) ?: [], fn (string $term) => $words($term) >= self::MIN_CHARACTERS);
            $search = array_filter($terms, fn (string $term) => !str_starts_with($term, '-')) ? implode(' ', $terms) : '';
        }
        if (preg_match_all('/[\p{L}\p{N}]/u', $search) < self::MIN_CHARACTERS) {
            return ['data' => [], 'next_cursor' => null, 'too_short' => true];
        }

        $page = SlackMessage::query()
            ->whereIn('slack_channel_id', SlackChannel::visibleTo($user)->select('id'))
            ->whereRaw(self::PREFIX_QUERY, [mb_strtolower($search)])
            ->where(fn ($query) => $query->whereNull('subtype')->orWhereNotIn('subtype', GetSlackChannelMessages::SYSTEM_SUBTYPES))
            ->when($filters['channel'] ?? null, fn ($query, $channelId) => $query->where('slack_channel_id', $channelId))
            ->when($filters['person'] ?? null, fn ($query, $slackUserId) => $query->where('slack_user_id', $slackUserId))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('sent_at', '>=', Carbon::parse($from)))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('sent_at', '<=', Carbon::parse($to)))
            ->when($filters['has_file'] ?? false, fn ($query) => $query->whereRaw("(data->'files') @> '[{}]'::jsonb"))
            ->with([
                'slackUser:id,slack_id,user_id,name,real_name,display_name,is_bot',
                'channel:id,name',
            ])
            ->orderByDesc('ts')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['id', 'slack_channel_id', 'ts', 'thread_ts', 'slack_user_id', 'user_id', 'subtype', 'text', 'sent_at', 'data'], GetSlackChannelMessages::CURSOR_NAME);

        return [
            'data'        => $this->rows($page->getCollection(), $this->terms($search)),
            'next_cursor' => $page->nextCursor()?->encode(),
            'too_short'   => false,
        ];
    }

    /**
     * @param  Collection<int, SlackMessage>  $messages
     * @param  list<string>  $terms
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $messages, array $terms): array
    {
        $nodes   = FormatSlackMessages::make()->handle($messages);
        $userIds = $messages->map(fn (SlackMessage $message) => $this->linkedUserId($message))->filter()->unique()->values()->all();
        $users   = $userIds === [] ? collect() : User::whereIn('id', $userIds)->with('image')
            ->get(['id', 'username', 'nickname', 'contact_name', 'image_id'])->keyBy('id');

        return $messages->map(function (SlackMessage $message) use ($nodes, $users, $terms) {
            $user    = $users->get($this->linkedUserId($message) ?? 0);
            $isReply = $message->thread_ts !== null && $message->thread_ts !== $message->ts && $message->subtype !== 'thread_broadcast';

            return [
                'id'       => $message->id,
                'channel'  => ['id' => $message->slack_channel_id, 'name' => $message->channel?->name],
                'author'   => [
                    'name'   => $user?->chatName() ?? $this->slackName($message),
                    'avatar' => $user?->image_id ? User::chatAvatarOf($user->imageSources(0, 48)) : null,
                    'is_bot' => $this->isBot($message),
                ],
                'sent_at'  => $message->sent_at?->toIso8601String(),
                'is_reply' => $isReply,
                'segments' => $this->segments(RefreshSlackMessageSearch::readableText($nodes[$message->id] ?? [], $message->data ?? []), $terms),
                'url'      => self::messageUrl($message),
            ];
        })->values()->all();
    }

    /**
     * Opens the channel at the message; a thread reply opens its thread too, scrolled to the reply.
     */
    public static function messageUrl(SlackMessage $message): string
    {
        $isReply = $message->thread_ts !== null && $message->thread_ts !== $message->ts && $message->subtype !== 'thread_broadcast';

        return route('grp.chat.staff.slack.show', array_filter([
            'slackChannel' => $message->slack_channel_id,
            'around'       => $isReply ? $message->thread_ts : $message->ts,
            'thread'       => $isReply ? $message->thread_ts : null,
            'reply'        => $isReply ? $message->ts : null,
        ]));
    }

    /**
     * The words to mark in a result: the query's words, without excluded ones (-word) and the "or" operator.
     *
     * @return list<string>
     */
    private function terms(string $search): array
    {
        $included = (string) preg_replace('/(^|\s)-\S+/u', ' ', $search);
        $words    = preg_split('/[^\p{L}\p{N}]+/u', RefreshSlackMessageSearch::normalise($included), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $word) => $word !== 'or')));
    }

    /**
     * The message as text, cut to a window around the first match, as segments to print with the matches marked.
     *
     * @param  list<string>  $terms
     * @return list<array{text: string, hit: bool}>
     */
    private function segments(string $text, array $terms): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches, PREG_OFFSET_CAPTURE);
        $words = array_map(fn (array $match) => [
            'start' => $match[1],
            'end'   => $match[1] + strlen($match[0]),
            'hit'   => $this->isHit($match[0], $terms),
        ], $matches[0]);

        $firstHit = array_key_first(array_filter($words, fn (array $word) => $word['hit'])) ?? 0;
        $from     = $words === [] ? 0 : $words[max(0, $firstHit - 8)]['start'];
        $to       = strlen($text);
        foreach ($words as $index => $word) {
            if ($index > $firstHit && $word['end'] - $from > self::SNIPPET_BYTES) {
                $to = $words[$index - 1]['end'];
                break;
            }
        }

        $segments = [];
        $add      = function (string $piece, bool $hit) use (&$segments) {
            if ($piece === '') {
                return;
            }
            $last = array_key_last($segments);
            if ($last !== null && $segments[$last]['hit'] === $hit && !$hit) {
                $segments[$last]['text'] .= $piece;
            } else {
                $segments[] = ['text' => $piece, 'hit' => $hit];
            }
        };

        $add($from > 0 ? '… ' : '', false);
        $cursor = $from;
        foreach ($words as $word) {
            if ($word['start'] < $from || $word['end'] > $to) {
                continue;
            }
            $add(substr($text, $cursor, $word['start'] - $cursor), false);
            $add(substr($text, $word['start'], $word['end'] - $word['start']), $word['hit']);
            $cursor = $word['end'];
        }
        $add(substr($text, $cursor, $to - $cursor), false);
        $add($to < strlen($text) ? ' …' : '', false);

        return $segments;
    }

    /**
     * @param  list<string>  $terms
     */
    private function isHit(string $word, array $terms): bool
    {
        $normalised = RefreshSlackMessageSearch::normalise($word);
        foreach ($terms as $term) {
            if (str_starts_with($normalised, $term)) {
                return true;
            }
        }

        return false;
    }

    private function isBot(SlackMessage $message): bool
    {
        return ($message->slackUser?->is_bot ?? false) || isset($message->data['bot_id']);
    }

    private function linkedUserId(SlackMessage $message): ?int
    {
        return $this->isBot($message) ? null : ($message->user_id ?? $message->slackUser?->user_id);
    }

    private function slackName(SlackMessage $message): ?string
    {
        $data = $message->data ?? [];
        if ($this->isBot($message)) {
            $botName = $data['username'] ?? $data['bot_profile']['name'] ?? null;
            if (is_string($botName) && $botName !== '') {
                return $botName;
            }
        }

        return $message->slackUser ? $this->personName($message->slackUser) : null;
    }

    private function personName(SlackUser $slackUser): ?string
    {
        return $slackUser->display_name ?: $slackUser->real_name ?: $slackUser->name;
    }

    /**
     * The people the person filter offers: nobody for a user who may not read Slack here.
     *
     * @return list<array{slack_id: string, name: string}>
     */
    public function people(User $user): array
    {
        if (!SlackChannel::userMayReadPublicChannels($user)) {
            return [];
        }

        $slackUsers = SlackUser::query()->get(['slack_id', 'user_id', 'name', 'real_name', 'display_name', 'is_bot']);
        $userIds    = $slackUsers->where('is_bot', false)->pluck('user_id')->filter()->unique()->values()->all();
        $users      = $userIds === [] ? collect() : User::whereIn('id', $userIds)->get(['id', 'username', 'nickname', 'contact_name'])->keyBy('id');

        return $slackUsers
            ->map(fn (SlackUser $slackUser) => [
                'slack_id' => $slackUser->slack_id,
                'name'     => (!$slackUser->is_bot && $slackUser->user_id ? $users->get($slackUser->user_id)?->chatName() : null) ?? $this->personName($slackUser) ?? $slackUser->slack_id,
            ])
            ->sortBy(fn (array $person) => mb_strtolower($person['name']))
            ->values()
            ->all();
    }

    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null, too_short: bool}
     */
    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->user(), $request->validated());
    }

    /**
     * @return list<array{slack_id: string, name: string}>
     */
    public function inPeople(ActionRequest $request): array
    {
        return $this->people($request->user());
    }
}
