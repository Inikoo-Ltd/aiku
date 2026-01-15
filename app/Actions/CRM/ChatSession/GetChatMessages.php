<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Http\Resources\CRM\Livechat\ChatMessageResource;

class GetChatMessages
{
    use AsAction;


    public function rules(): array
    {
        return [
            'is_read' => ['sometimes', 'boolean'],
            'translation_language_id' => ['sometimes', 'integer', 'exists:languages,id'],
            'sender_type' => [
                'sometimes',
                Rule::in(array_column(ChatSenderTypeEnum::cases(), 'value')),
            ],
            'request_from' => [
                'sometimes',
                'string',
                Rule::in([
                    ChatSenderTypeEnum::AGENT->value,
                    ChatSenderTypeEnum::USER->value,
                    ChatSenderTypeEnum::GUEST->value,
                ])
            ],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'date'],
        ];
    }


    public function asController(ChatSession $chatSession, ActionRequest $request): array
    {
        $validated = $request->validated();

        if (array_key_exists('request_from', $validated)) {
            $requestFrom = $validated['request_from'] ?? ChatSenderTypeEnum::GUEST->value;
            $readerType = ChatSenderTypeEnum::tryFrom($requestFrom) ?? ChatSenderTypeEnum::GUEST;
            MarkChatMessagesAsRead::run($chatSession, $readerType);
        }

        $messages = $this->handle($chatSession, $validated);

        $nextCursor = null;

        if ($messages->isNotEmpty()) {
            $oldest = $messages->first();
            $nextCursor = $oldest->created_at->toISOString();
        }

        $hasMore = $this->hasMore($chatSession, $nextCursor);

        return [
            'chatSession' => $chatSession,
            'messages' => $messages,
            'pagination' => [
                'has_more'    => $hasMore,
                'next_cursor' => $hasMore ? $nextCursor : null,
                'count'       => $messages->count(),
                'limit'       => $validated['limit'] ?? 20,
            ]
        ];
    }



    public function handle(ChatSession $chatSession, array $filters)
    {
        $query = $chatSession->messages()
            ->with([
                'media',
                'translations' => function ($query) use ($filters) {
                    $query->with('targetLanguage');
                    if (!empty($filters['translation_language_id'])) {
                        $query->where('target_language_id', $filters['translation_language_id']);
                    }
                },
                'originalLanguage',
                'attachment',
                'chatSession.assignments.chatAgent.user'
            ])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['cursor'])) {
            $query->where('created_at', '<', $filters['cursor']);
        }

        if (isset($filters['is_read'])) {
            $query->where('is_read', $filters['is_read']);
        }

        if (!empty($filters['sender_type'])) {
            $query->where('sender_type', $filters['sender_type']);
        }

        $limit = $filters['limit'] ?? 20;

        return $query->limit($limit)->get()->sortBy('created_at')->values();
    }


    public function jsonResponse($result): JsonResponse
    {
        $fullName = $result['chatSession']->assignments->last()?->chatAgent?->user?->contact_name
            ?? $result['chatSession']->assignments->last()?->chatAgent?->user?->username
            ?? null;

        $firstName = $fullName ? explode(' ', trim($fullName))[0] : null;
        return response()->json([
            'success' => true,
            'message' => 'Chat messages retrieved successfully',
            'data' => [
                'session_ulid'   => $result['chatSession']->ulid,
                'session_status' => $result['chatSession']->status->value,
                'assigned_agent' => $firstName,
                'rating'         => $result['chatSession']->rating,
                'messages'       => ChatMessageResource::collection($result['messages']),
                'pagination'     => $result['pagination'],
            ]
        ]);
    }

    private function hasMore(ChatSession $chatSession, ?string $cursor): bool
    {
        if (!$cursor) {
            return false;
        }

        return $chatSession->messages()
            ->where('created_at', '<', $cursor)
            ->exists();
    }
}
