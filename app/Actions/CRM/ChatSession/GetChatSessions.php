<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionListResource;

class GetChatSessions
{
    use AsAction;

    public function handle(array $filters = [])
    {

        $query = ChatSession::with([
            'messages' => function ($query) {
                $query->latest()->limit(1);
            },
            'webUser',
            'assignments.chatAgent.user'
        ])->latest()->get();


 // Filter by status
        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['statuses']) && is_array($filters['statuses'])) {
            $query->whereIn('status', $filters['statuses']);
        }

        if (isset($filters['assigned_to_me']) && $filters['assigned_to_me']) {
            $currentAgent = $this->getCurrentAgent();
            if ($currentAgent) {
                $query->whereHas('assignments', function ($q) use ($currentAgent) {
                    $q->where('chat_agent_id', $currentAgent->id)
                      ->where('status', 'active');
                });
            }
        }

        if (isset($filters['has_unread']) && $filters['has_unread']) {
            $query->whereHas('messages', function ($q) {
                $q->where('is_read', false);
            });
        }

        $limit = $filters['limit'] ?? 20;

        $sessions = $query->paginate($limit);

        return $sessions;
    }

    public function asController(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $sessions = $this->handle($validated);

        return $this->jsonResponse($sessions);
    }

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
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function getCurrentAgent()
    {
        if (auth()->check()) {
            $user = auth()->user();
            return ChatAgent::where('user_id', $user->id)->first();
        }
        return null;
    }

    public function jsonResponse($sessions): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Chat sessions retrieved successfully',
            'data' => [
                'sessions' => ChatSessionListResource::collection($sessions),
                'pagination' => [
                    'current_page' => $sessions->currentPage(),
                    'per_page' => $sessions->perPage(),
                    'total' => $sessions->total(),
                    'last_page' => $sessions->lastPage(),
                    'has_more' => $sessions->hasMorePages(),
                ]
            ]
        ]);
    }
}
