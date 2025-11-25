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
use App\Enums\CRM\Livechat\ChatEventActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;

class AssignChatToAgent
{
    use AsAction;


         public function handle(ChatSession $chatSession, int $agentId, int $assignedByAgentId): ChatAssignment
        {

            $agent = ChatAgent::findOrFail($agentId);
            if (!$agent->isAvailableForChat()) {
                throw new Exception('Agent is not available for new chats.');
            }

            $chatSession->update([
                'status' => ChatSessionStatusEnum::ACTIVE->value
            ]);

            $chatAssignment = ChatAssignment::create([
                'chat_session_id' => $chatSession->id,
                'chat_agent_id' => $agentId,
                'status' => ChatAssignmentStatusEnum::ACTIVE->value,
                'assigned_by' => ChatAssignmentAssignedByEnum::AGENT->value,
                'assigned_at' => now(),
            ]);

            $agent->incrementChatCount();

            $this->logTransferRequestEvent($chatSession, $assignedByAgentId, $agentId);

            return $chatAssignment;
        }


        public function rules(): array
        {
            return [
                'agent_id' => [
                    'required',
                    'exists:chat_agents,id'
                ],
            ];
        }

        public function asController(ChatSession $chatSession, Request $request): JsonResponse
        {
            $assignedByAgent = $this->getCurrentAgent();

            if (!$assignedByAgent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only agents can assign chats'
                ], 403);
            }

            $validated = $request->validate($this->rules());

            try {
                $chatAssignment = $this->handle(
                    $chatSession,
                    $validated['agent_id'],
                    $assignedByAgent->id
                );

                return $this->jsonResponse($chatAssignment);

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


        public function assignToSelf(ChatSession $chatSession): JsonResponse
        {
            $agent = $this->getCurrentAgent();

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only agents can assign chats'
                ], 403);
            }

            try {
                $chatAssignment = $this->handle($chatSession, $agent->id, $agent->id);

                return $this->jsonResponse($chatAssignment);

            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }
        }


    protected function logTransferRequestEvent(ChatSession $chatSession, int $fromAgentId, int $toAgentId): void
    {
        ChatEvent::create([
            'chat_session_id' => $chatSession->id,
            'event_type' => ChatEventTypeEnum::TRANSFER_REQUEST->value,
            'actor_type' => ChatActorTypeEnum::AGENT->value,
            'actor_id' => $fromAgentId,
            'payload' => [
                'from_agent_id' => $fromAgentId,
                'to_agent_id' => $toAgentId,
                'assigned_at' => now()->toISOString(),
                'session_ulid' => $chatSession->ulid,
                'session_previous_status' => $chatSession->getOriginal('status'),
                'session_new_status' => ChatSessionStatusEnum::ACTIVE->value,
            ],
        ]);
    }

    public function jsonResponse(ChatAssignment $chatAssignment): JsonResponse
    {
        $chatSession = $chatAssignment->chatSession;
        $agent = $chatAssignment->chatAgent;

        return response()->json([
            'success' => true,
            'message' => 'Chat assigned to agent successfully',
            'data' => [
                'assignment' => [
                    'id' => $chatAssignment->id,
                    'status' => $chatAssignment->status,
                    'assigned_at' => $chatAssignment->assigned_at->toISOString(),
                    'assigned_by' => $chatAssignment->assigned_by,
                ],
                'session' => [
                    'ulid' => $chatSession->ulid,
                    'status' => $chatSession->status,
                    'guest_identifier' => $chatSession->guest_identifier,
                    'web_user' => $chatSession->webUser ? [
                        'name' => $chatSession->webUser->name,
                        'email' => $chatSession->webUser->email,
                    ] : null,
                ],
                'agent' => [
                    'id' => $agent->id,
                    'name' => $agent->user->name,
                    'current_chat_count' => $agent->current_chat_count,
                ]
            ]
        ]);
    }


    public function htmlResponse(ChatAssignment $chatAssignment): JsonResponse
    {
        return $this->jsonResponse($chatAssignment);
    }


}