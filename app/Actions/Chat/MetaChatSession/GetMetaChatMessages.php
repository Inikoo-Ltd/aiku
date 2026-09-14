<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\ChatTimelineEventResource;
use App\Http\Resources\CRM\Livechat\MetaChatMessageResource;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMetaChatMessages
{
    use AsAction;

    public function rules(): array
    {
        return [
            'limit'  => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'date'],
        ];
    }

    public function handle(MetaChatSession $metaChatSession, array $filters): Collection
    {
        $query = $metaChatSession->messages()
            ->with('attachment')
            ->orderBy('created_at', 'desc');

        if (!empty($filters['cursor'])) {
            $query->where('created_at', '<', $filters['cursor']);
        }

        $limit = $filters['limit'] ?? 20;

        return $query->limit($limit)->get()->sortBy('created_at')->values();
    }

    public function asController(MetaChatSession $metaChatSession, ActionRequest $request): array
    {
        $validated = $request->validated();

        MarkMetaChatMessagesAsRead::run($metaChatSession);

        $messages = $this->handle($metaChatSession, $validated);

        $nextCursor = null;
        if ($messages->isNotEmpty()) {
            $nextCursor = $messages->first()->created_at->toISOString();
        }

        $hasMore = $nextCursor && $metaChatSession->messages()
            ->where('created_at', '<', $nextCursor)
            ->exists();

        return [
            'metaChatSession' => $metaChatSession,
            'messages'        => $messages,
            'events'          => $this->timelineEvents($metaChatSession, $messages, $validated),
            'pagination'      => [
                'has_more'    => $hasMore,
                'next_cursor' => $hasMore ? $nextCursor : null,
                'count'       => $messages->count(),
                'limit'       => $validated['limit'] ?? 20,
            ]
        ];
    }

    /**
     * Windowed to the messages on this page, so older events arrive as the agent scrolls
     * back instead of needing a cursor of their own.
     */
    protected function timelineEvents(MetaChatSession $metaChatSession, Collection $messages, array $filters): Collection
    {
        if ($messages->isEmpty()) {
            return collect();
        }

        $query = $metaChatSession->events()
            ->whereIn('event_type', ChatEventTypeEnum::timelineTypes())
            ->where('created_at', '>=', $messages->first()->created_at);

        if (!empty($filters['cursor'])) {
            $query->where('created_at', '<', $filters['cursor']);
        }

        return $query->orderBy('created_at')->get();
    }

    public function jsonResponse(array $result): JsonResponse
    {
        $session = $result['metaChatSession'];
        $status  = $session->status;

        if ($status !== ChatSessionStatusEnum::CLOSED) {
            $hasActiveAssignment = $session->assignments()
                ->where('status', ChatAssignmentStatusEnum::ACTIVE)
                ->exists();

            if (!$hasActiveAssignment) {
                $status = ChatSessionStatusEnum::WAITING;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Chat messages retrieved successfully',
            'data'    => [
                'session_ulid'   => $session->ulid,
                'session_status' => $status->value,
                'can_send_non_template_message' => $session->can_send_non_template_message,
                'messages'       => MetaChatMessageResource::collection($result['messages']),
                'events'         => ChatTimelineEventResource::collection($result['events']),
                'pagination'     => $result['pagination'],
            ]
        ]);
    }
}
