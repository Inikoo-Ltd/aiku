<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
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

        $metaChatSession->messages()
            ->where('is_read', false)
            ->whereIn('sender_type', [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value])
            ->update(['is_read' => true, 'read_at' => now()]);

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
            'pagination'      => [
                'has_more'    => $hasMore,
                'next_cursor' => $hasMore ? $nextCursor : null,
                'count'       => $messages->count(),
                'limit'       => $validated['limit'] ?? 20,
            ]
        ];
    }

    public function jsonResponse(array $result): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Chat messages retrieved successfully',
            'data'    => [
                'session_ulid'   => $result['metaChatSession']->ulid,
                'session_status' => $result['metaChatSession']->status->value,
                'messages'       => MetaChatMessageResource::collection($result['messages']),
                'pagination'     => $result['pagination'],
            ]
        ]);
    }
}
