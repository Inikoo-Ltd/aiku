<?php

namespace App\Actions\CRM\ChatSession;

use Exception;
use Illuminate\Http\JsonResponse;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatEvent;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\Livechat\ChatAssignment;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;

class CloseChatSession
{
    use AsAction;

     public function handle(ChatSession $chatSession, int $closedByAgentId): ChatSession
    {
        $chatSession->update([
            'status' => ChatSessionStatusEnum::CLOSED->value,
            'closed_at' => now(),
        ]);

        $activeAssignment = ChatAssignment::where('chat_session_id', $chatSession->id)
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->first();

        if ($activeAssignment) {
            $activeAssignment->update([
                'status' => ChatAssignmentStatusEnum::RESOLVED->value,
                'resolved_at' => now(),
            ]);

            $agent = ChatAgent::find($activeAssignment->chat_agent_id);
            if ($agent) {
                $agent->decrementChatCount();
            }
        }

        $this->logCloseEvent($chatSession, $closedByAgentId, $activeAssignment);

        return $chatSession->fresh();
    }

    public function asController(ChatSession $chatSession): JsonResponse
    {
        $closedByAgent = $this->getCurrentAgent();

        if (!$closedByAgent) {
            return response()->json([
                'success' => false,
                'message' => 'Only agents can close chat sessions'
            ], 403);
        }

        try {
            $closedSession = $this->handle($chatSession, $closedByAgent->id);

            return $this->jsonResponse($closedSession);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    protected function getCurrentAgent(): ?ChatAgent
    {
        if (auth()->check()) {
            $user = auth()->user();
            return ChatAgent::where('user_id', $user->id)->first();
        }

        return null;
    }

    protected function logCloseEvent(ChatSession $chatSession, int $agentId, ?ChatAssignment $assignment): void
    {
        $payload = [
            'closed_by_agent_id' => $agentId,
            'closed_at' => now()->toISOString(),
            'session_duration' => $chatSession->created_at->diffInMinutes(now()),
            'session_ulid' => $chatSession->ulid,
        ];

        if ($assignment) {
            $payload['assignment'] = [
                'assignment_id' => $assignment->id,
                'assigned_agent_id' => $assignment->chat_agent_id,
                'assigned_at' => $assignment->assigned_at->toISOString(),
                'assignment_duration' => $assignment->assigned_at->diffInMinutes(now()),
            ];
        }

        if ($chatSession->web_user_id) {
            $payload['user_type'] = 'authenticated';
            $payload['web_user_id'] = $chatSession->web_user_id;
        } else {
            $payload['user_type'] = 'guest';
            $payload['guest_identifier'] = $chatSession->guest_identifier;
        }

        ChatEvent::create([
            'chat_session_id' => $chatSession->id,
            'event_type' => ChatEventTypeEnum::CLOSE->value,
            'actor_type' => ChatActorTypeEnum::AGENT->value,
            'actor_id' => $agentId,
            'payload' => $payload,
        ]);
    }

    public function jsonResponse(ChatSession $chatSession): JsonResponse
    {
        $sessionDuration = $chatSession->created_at->diffInMinutes($chatSession->closed_at);

        return response()->json([
            'success' => true,
            'message' => 'Chat session closed successfully',
            'data' => [
                'session' => [
                    'ulid' => $chatSession->ulid,
                    'status' => $chatSession->status,
                    'closed_at' => $chatSession->closed_at->toISOString(),
                    'session_duration_minutes' => $sessionDuration,
                    'is_guest' => is_null($chatSession->web_user_id),
                    'guest_identifier' => $chatSession->guest_identifier,
                    'web_user_id' => $chatSession->web_user_id,
                ],
                'assignment' => [
                    'resolved' => true,
                    'resolved_at' => $chatSession->closed_at->toISOString(),
                ]
            ]
        ]);
    }


    public function htmlResponse(ChatSession $chatSession): JsonResponse
    {
        return $this->jsonResponse($chatSession);
    }
}