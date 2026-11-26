<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-14h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Chat\Staff\Json;

use App\Actions\Chat\Staff\Slack\GetSlackChannelMessages;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The "Files & links" tab of a Slack channel: every file, picture and web link shared in it, thread replies included,
 * newest or oldest first. Files and links are shaped by the stream itself (GetSlackChannelMessages::rows), so the same
 * access rules and safe-URL checks apply. Pages are counted in messages and continued from the last message's ts.
 */
class GetSlackChannelFilesAndLinks
{
    use AsAction;

    public const int PER_PAGE = 40;

    public const array KINDS = ['all', 'files', 'media', 'links'];

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('slackChannel')->isVisibleTo($request->user());
    }

    public function rules(): array
    {
        return [
            'kind'   => ['sometimes', 'string', 'in:'.implode(',', self::KINDS)],
            'q'      => ['sometimes', 'nullable', 'string', 'max:200'],
            'sort'   => ['sometimes', 'string', 'in:newest,oldest'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^\d+(\.\d+)?$/'],
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function handle(SlackChannel $slackChannel, string $kind = 'all', ?string $search = null, string $sort = 'newest', ?string $cursor = null): array
    {
        $search  = trim((string) $search);
        $newest  = $sort !== 'oldest';
        $wantsFiles = $kind !== 'links';
        $wantsLinks = in_array($kind, ['all', 'links'], true);

        $messages = SlackMessage::where('slack_channel_id', $slackChannel->id)
            ->where(function (Builder $query) use ($wantsFiles, $wantsLinks) {
                if ($wantsFiles) {
                    $query->orWhereRaw("jsonb_typeof(data->'files') = 'array' and jsonb_array_length(data->'files') > 0");
                }
                if ($wantsLinks) {
                    $query->orWhere('text', '~*', 'https?://')->orWhereRaw("(data->'blocks')::text ~* 'https?://'");
                }
            })
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query->where('text', 'ilike', $like)->orWhereRaw("(data->'files')::text ilike ?", [$like]));
            })
            ->when($cursor, fn (Builder $query) => $query->where('ts', $newest ? '<' : '>', $cursor))
            ->with(GetSlackChannelMessages::relations())
            ->orderBy('ts', $newest ? 'desc' : 'asc')
            ->limit(self::PER_PAGE + 1)
            ->get(GetSlackChannelMessages::COLUMNS);

        $hasMore  = $messages->count() > self::PER_PAGE;
        $messages = $messages->take(self::PER_PAGE)->values();
        $needle   = mb_strtolower($search);

        $items = [];
        foreach (GetSlackChannelMessages::make()->rows($slackChannel, $messages) as $row) {
            $shared = [
                'message_id'   => $row['id'],
                'ts'           => $row['ts'],
                'thread_ts'    => $row['thread_ts'],
                'is_broadcast' => $row['is_broadcast'],
                'sent_at'      => $row['sent_at'],
                'shared_by'    => $row['author']['name'] ?? null,
            ];

            if ($wantsFiles) {
                foreach ($row['files'] as $index => $file) {
                    $isMedia = $file['is_image'] || in_array($file['type'], ['MP4', 'MOV', 'WEBM', 'GIF', 'JPG', 'JPEG', 'PNG', 'WEBP', 'HEIC'], true);
                    if (($kind === 'files' && $isMedia) || ($kind === 'media' && !$isMedia)) {
                        continue;
                    }
                    if ($needle !== '' && !str_contains(mb_strtolower($file['name']), $needle)) {
                        continue;
                    }
                    $items[] = $shared + ['key' => 'file-'.$row['id'].'-'.$index, 'kind' => $isMedia ? 'media' : 'file', 'file' => $file];
                }
            }

            if ($wantsLinks) {
                foreach ($this->links($row['nodes']) as $index => $link) {
                    if ($needle !== '' && !str_contains(mb_strtolower($link['url'].' '.$link['text']), $needle)) {
                        continue;
                    }
                    $items[] = $shared + ['key' => 'link-'.$row['id'].'-'.$index, 'kind' => 'link', 'link' => $link];
                }
            }
        }

        return [
            'items'       => $items,
            'next_cursor' => $hasMore ? $messages->last()?->ts : null,
        ];
    }

    /**
     * The distinct web links of a message's formatted nodes; mailto links are left out.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array{url: string, text: string}>
     */
    private function links(array $nodes): array
    {
        $found = [];
        $walk  = function (array $nodes) use (&$walk, &$found) {
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                if (($node['type'] ?? null) === 'link' && is_string($node['url'] ?? null) && preg_match('/^https?:\/\//i', $node['url'])) {
                    $found[$node['url']] ??= ['url' => $node['url'], 'text' => is_string($node['text'] ?? null) && $node['text'] !== '' ? $node['text'] : $node['url']];
                }
                if (is_array($node['children'] ?? null)) {
                    $walk($node['children']);
                }
                if (is_array($node['items'] ?? null)) {
                    $walk($node['items']);
                }
            }
        };
        $walk($nodes);

        return array_values($found);
    }

    /**
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        return $this->handle(
            $slackChannel,
            $request->validated('kind', 'all'),
            $request->validated('q'),
            $request->validated('sort', 'newest'),
            $request->validated('cursor'),
        );
    }
}
