<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
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
            'view_team'       => ['sometimes', 'boolean'],
            'limit'           => ['sometimes', 'integer', 'min:1', 'max:50'],
            'web_user_id'     => ['sometimes', 'integer', 'exists:web_users,id'],
            'search'          => ['sometimes', 'string', 'max:100'],
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
            'chatEvents' => function ($q) {
                $q->where('event_type', ChatEventTypeEnum::GUEST_PROFILE)->latest()->limit(1);
            },
            'webUser',
            'shop',
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
            $userId       = (int) $filters['assigned_to_me'];
            $currentAgent = $this->getCurrentAgent($userId);

            if ($currentAgent) {
                $shopIds = $currentAgent->shops()->pluck('shops.id');

                if (!empty($filters['view_team'])) {
                    $teamAgentIds = ChatAgent::whereHas('shops', function ($q) use ($shopIds) {
                        $q->whereIn('shops.id', $shopIds);
                    })->where('id', '!=', $currentAgent->id)->pluck('id');

                    $requestedStatuses = (array) ($filters['statuses'] ?? ($filters['status'] ? [$filters['status']] : []));
                    $isClosed          = in_array('closed', $requestedStatuses);
                    $assignmentStatus  = $isClosed
                        ? ChatAssignmentStatusEnum::RESOLVED->value
                        : ChatAssignmentStatusEnum::ACTIVE->value;

                    $query->whereHas('assignments', function ($assignmentQ) use ($teamAgentIds, $assignmentStatus) {
                        $assignmentQ->whereIn('chat_agent_id', $teamAgentIds)
                            ->where('status', $assignmentStatus);
                    });
                } else {
                    $query->where(function ($q) use ($currentAgent, $shopIds) {
                        $q->where(function ($sub) use ($shopIds) {
                            $sub->whereIn('shop_id', $shopIds)
                                ->where('status', ChatSessionStatusEnum::WAITING);
                        })->orWhereHas('assignments', function ($assignmentQ) use ($currentAgent) {
                            $assignmentQ->where('chat_agent_id', $currentAgent->id);
                        });
                    });
                }
            }
        }

        if (isset($filters['web_user_id'])) {
            $query->where('web_user_id', $filters['web_user_id']);
        }

        if (!empty($filters['search'])) {
            $term = mb_strtolower($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(chat_sessions.guest_identifier COLLATE "C") LIKE ?', ["%{$term}%"])
                    ->orWhereHas('webUser', function ($q2) use ($term) {
                        $q2->whereRaw('LOWER(username COLLATE "C") LIKE ?', ["%{$term}%"])
                            ->orWhereHas('customer', function ($q3) use ($term) {
                                $q3->whereRaw('LOWER(contact_name COLLATE "C") LIKE ?', ["%{$term}%"]);
                            });
                    });
            });
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
