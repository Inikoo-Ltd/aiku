<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Http\Resources\CRM\Livechat\ChatMessageResource;

class GetChatMessages
{
    use AsAction;

     public function handle(ChatSession $chatSession, array $filters = [])
    {
        $query = $chatSession->messages()
            ->with(['media'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['cursor']) && $filters['cursor']) {
            $query->where('created_at', '<', $filters['cursor']);
        }

        if (isset($filters['is_read'])) {
            $query->where('is_read', $filters['is_read']);
        }

        if (isset($filters['sender_type'])) {
            $query->where('sender_type', $filters['sender_type']);
        }

        $limit = $filters['limit'] ?? 20;
        $messages = $query->limit($limit)->get();

        return $messages->sortBy('created_at');
    }

    public function asController(Request $request, $ulid)
    {
        $this->validateUlid($ulid);

        $validated = $request->validate($this->queryRules());

        $chatSession = ChatSession::where('ulid', $ulid)->first();

        $messages = $this->handle($chatSession, $validated);

        $nextCursor = null;
        if ($messages->isNotEmpty()) {
            $oldestMessage = $messages->first();
            $nextCursor = $oldestMessage->created_at->toISOString();
        }

        $hasMore = $this->hasMoreMessages($chatSession, $nextCursor);

        return [
            'success' => true,
            'message' => 'Chat messages retrieved successfully',
            'data' => [
                'session_ulid' => $chatSession->ulid,
                'session_status' => $chatSession->status->value,
                'messages' => ChatMessageResource::collection($messages),
                'pagination' => [
                    'has_more' => $hasMore,
                    'next_cursor' => $hasMore ? $nextCursor : null,
                    'count' => $messages->count(),
                    'limit' => $validated['limit'] ?? 20,
                ]
            ]
        ];
    }

    protected function validateUlid($ulid): void
    {
        validator(
            ['session_ulid' => $ulid],
            $this->ulidRules()
        )->validate();
    }

    protected function ulidRules(): array
    {
        return [
            'session_ulid' => [
                'required',
                'string',
                'ulid',
                Rule::exists('chat_sessions', 'ulid')
            ]
        ];
    }

    protected function queryRules(): array
    {
        return [
            'is_read' => ['sometimes', 'boolean'],
            'sender_type' => [
                'sometimes',
                Rule::in(array_column(ChatSenderTypeEnum::cases(), 'value'))
            ],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'date'],
        ];
    }


    public function jsonResponse($result): JsonResponse
    {
        return response()->json($result);
    }

    private function hasMoreMessages(ChatSession $chatSession, ?string $cursor): bool
    {
        if (!$cursor) {
            return false;
        }

        return $chatSession->messages()
            ->where('created_at', '<', $cursor)
            ->exists();
    }
}