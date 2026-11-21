<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Json;

use App\Actions\Chat\Staff\Slack\GetSlackChannelMessages;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One Slack thread for the side panel: the parent (a deleted one shows as deleted) on the first page, then its
 * replies oldest first, paged by cursor.
 */
class GetSlackThread
{
    use AsAction;

    public const int PER_PAGE = 50;

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('slackChannel')->isVisibleTo($request->user());
    }

    public function rules(): array
    {
        return [
            'ts'     => ['required', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'],
            'cursor' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array{parent: array<string, mixed>|null, replies: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function handle(SlackChannel $slackChannel, string $ts, int $perPage = self::PER_PAGE): array
    {
        $parent = SlackMessage::withTrashed()
            ->where('slack_channel_id', $slackChannel->id)
            ->where('ts', $ts)
            ->where(fn ($query) => $query->whereNull('thread_ts')->orWhereColumn('thread_ts', 'ts'))
            ->with(GetSlackChannelMessages::relations())
            ->firstOrFail(GetSlackChannelMessages::COLUMNS);

        $page = SlackMessage::where('slack_channel_id', $slackChannel->id)
            ->where('thread_ts', $ts)
            ->where('ts', '!=', $ts)
            ->with(GetSlackChannelMessages::relations())
            ->orderBy('ts')
            ->cursorPaginate($perPage, GetSlackChannelMessages::COLUMNS, GetSlackChannelMessages::CURSOR_NAME);

        $isFirstPage = !request()->filled(GetSlackChannelMessages::CURSOR_NAME);
        $messages    = $isFirstPage ? $page->getCollection()->prepend($parent) : $page->getCollection();
        $rows        = GetSlackChannelMessages::make()->rows($slackChannel, $messages);

        return [
            'parent'      => $isFirstPage ? array_shift($rows) : null,
            'replies'     => $rows,
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * @return array{parent: array<string, mixed>|null, replies: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        return $this->handle($slackChannel, $request->validated('ts'));
    }
}
