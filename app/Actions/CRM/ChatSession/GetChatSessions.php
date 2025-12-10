<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionListResource;

class GetChatSessions
{
    use AsAction;

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'string',
                'in:' . implode(',', array_column(ChatSessionStatusEnum::cases(), 'value'))
            ],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => [
                'string',
                'in:' . implode(',', array_column(ChatSessionStatusEnum::cases(), 'value'))
            ],
            'assigned_to_me' => ['sometimes', 'integer'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function asController(ActionRequest $request)
    {
        $filters = $request->validated();

        return $this->handle($filters);
    }

    public function handle(array $filters = [])
    {

        $query = ChatSession::with([
            'messages' => function ($q) {
                $q->latest()->limit(1);
            },
            'webUser',
            'assignments.chatAgent.user'
        ])
            ->whereHas('messages')
            ->withLastMessageTime()
            ->orderBy('last_message_at', 'desc');


        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['statuses'])) {
            $query->whereIn('status', $filters['statuses']);
        }
        if (!empty($filters['assigned_to_me'])) {

            $userId = (int) $filters['assigned_to_me'];

            $currentAgent = $this->getCurrentAgent($userId);

            if ($currentAgent) {
                $query->whereHas('assignments', function ($q) use ($currentAgent) {
                    $q->where('chat_agent_id', $currentAgent->id);
                });
            }
        }

        return $query->paginate($filters['limit'] ?? 20);
    }

    protected function getCurrentAgent(int $userId): ?ChatAgent
    {
        return ChatAgent::where('user_id', $userId)->first();
    }

    public function jsonResponse($sessions): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Chat sessions retrieved successfully',
            'data' => [
                'sessions'   => ChatSessionListResource::collection($sessions),
                'pagination' => [
                    'current_page' => $sessions->currentPage(),
                    'per_page'     => $sessions->perPage(),
                    'total'        => $sessions->total(),
                    'last_page'    => $sessions->lastPage(),
                    'has_more'     => $sessions->hasMorePages(),
                ]
            ]
        ]);
    }
}