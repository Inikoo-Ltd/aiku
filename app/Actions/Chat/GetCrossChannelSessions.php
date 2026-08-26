<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat;

use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\Chat\MetaChatSession\UI\GetMetaChatSessions;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionListResource;
use App\Http\Resources\CRM\Livechat\MetaChatSessionListResource;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Spam, trash and highlight are housekeeping views: the agent wants to clear the
 * backlog, not to think about which channel a conversation arrived on. This serves
 * them as one list, tagging every row with the channel it belongs to so the UI can
 * show an icon and route its actions to the right table.
 */
class GetCrossChannelSessions
{
    use AsAction;

    public function rules(): array
    {
        return [
            'statuses'        => ['sometimes', 'array'],
            'statuses.*'      => ['string', 'in:'.implode(',', array_column(ChatSessionStatusEnum::cases(), 'value'))],
            'assigned_to_me'  => ['sometimes', 'integer'],
            'view_team'       => ['sometimes', 'boolean'],
            'is_spam'         => ['sometimes', 'boolean'],
            'trashed'         => ['sometimes', 'boolean'],
            'highlighted'     => ['sometimes', 'boolean'],
            'page'            => ['sometimes', 'integer', 'min:1'],
            'limit'           => ['sometimes', 'integer', 'min:1', 'max:50'],
            'search'          => ['sometimes', 'string', 'max:100'],
            'organisation_id' => ['sometimes', 'integer', 'exists:organisations,id'],
            'shop_id'         => ['sometimes', 'integer', 'exists:shops,id'],
        ];
    }

    /**
     * Both sources are asked for everything up to the requested page, then merged and
     * sliced. Paging two tables independently cannot preserve a single chronological
     * order, and these views are small enough that over-fetching costs nothing.
     *
     * @return array{rows: Collection, page: int, limit: int, has_more: bool}
     */
    public function handle(array $filters = []): array
    {
        $page  = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(50, max(1, (int) ($filters['limit'] ?? 20)));
        $take  = min(200, $page * $limit);

        $sourceFilters = array_merge($filters, ['page' => 1, 'limit' => $take]);

        $website = GetChatSessions::make()->handle($sourceFilters);
        $meta    = GetMetaChatSessions::make()->handle($sourceFilters);

        $rows = collect($website->items())
            ->map(fn ($session) => ['channel' => 'website', 'session' => $session])
            ->concat(
                collect($meta->items())->map(fn ($session) => ['channel' => 'whatsapp', 'session' => $session])
            )
            ->sortByDesc(fn (array $row) => $this->lastActivityAt($row['session']))
            ->values();

        return [
            'rows'     => $rows->slice(($page - 1) * $limit, $limit)->values(),
            'page'     => $page,
            'limit'    => $limit,
            'has_more' => $rows->count() > $page * $limit || $website->hasMorePages() || $meta->hasMorePages(),
        ];
    }

    protected function lastActivityAt($session)
    {
        return $session->last_visitor_message_at
            ?? $session->last_agent_message_at
            ?? $session->created_at;
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->validated());
    }

    public function jsonResponse(array $result): JsonResponse
    {
        $sessions = $result['rows']->map(function (array $row) {
            $resource = $row['session'] instanceof MetaChatSession
                ? MetaChatSessionListResource::make($row['session'])
                : ChatSessionListResource::make($row['session']);

            return array_merge($resource->resolve(), ['channel' => $row['channel']]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Cross channel chat sessions retrieved successfully',
            'data'    => [
                'sessions'   => $sessions,
                'pagination' => [
                    'current_page' => $result['page'],
                    'per_page'     => $result['limit'],
                    'has_more'     => $result['has_more'],
                ],
            ],
        ]);
    }
}
