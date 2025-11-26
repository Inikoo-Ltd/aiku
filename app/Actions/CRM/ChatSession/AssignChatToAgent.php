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
        // Validasi agent exists
        $agent = ChatAgent::findOrFail($agentId);

        // Validasi agent available
        if (!$agent->isAvailableForChat()) {
            throw new Exception('Agent is not available for new chats.');
        }

        // Update session status
        $chatSession->update([
            'status' => ChatSessionStatusEnum::ACTIVE->value
        ]);

        // Deactivate previous assignments
        ChatAssignment::where('chat_session_id', $chatSession->id)
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->update([
                'status' => ChatAssignmentStatusEnum::INACTIVE->value,
                'ended_at' => now()
            ]);

        // Create new assignment
        $chatAssignment = ChatAssignment::create([
            'chat_session_id' => $chatSession->id,
            'chat_agent_id' => $agentId,
            'status' => ChatAssignmentStatusEnum::ACTIVE->value,
            'assigned_by' => ChatAssignmentAssignedByEnum::AGENT->value,
            'assigned_by_agent_id' => $assignedByAgentId,
            'assigned_at' => now(),
        ]);

        // Update agent chat count
        $agent->incrementChatCount();

        // Log event
        $this->logTransferRequestEvent($chatSession, $assignedByAgentId, $agentId);

        return $chatAssignment;
    }

    public function rules(): array
    {
        return [
            'agent_id' => [
                'required',
                'integer',
                'exists:chat_agents,id'
            ],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'agent_id.required' => 'Agent ID is required',
            'agent_id.integer' => 'Agent ID must be an integer',
            'agent_id.exists' => 'Agent not found',
        ];
    }

    public function asController(ChatSession $chatSession, Request $request): JsonResponse
    {
        $this->validateUlid($chatSession->ulid);

        $assignedByAgent = $this->getCurrentAgent();

        if (!$assignedByAgent) {
            return response()->json([
                'success' => false,
                'message' => 'Only authenticated agents can assign chats'
            ], 403);
        }

        $validated = $request->validate($this->rules(), $this->getValidationMessages());

        try {
            $this->handle(
                $chatSession,
                $validated['agent_id'],
                $assignedByAgent->id
            );

            return $this->jsonResponse();

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    protected function validateUlid($ulid): void
    {
        validator(
            ['session_ulid' => $ulid],
            [
                'session_ulid' => [
                    'required',
                    'string',
                    'ulid',
                    'exists:chat_sessions,ulid'
                ]
            ],
            [
                'session_ulid.required' => 'Session ULID is required',
                'session_ulid.ulid' => 'Invalid ULID format',
                'session_ulid.exists' => 'Chat session not found',
            ]
        )->validate();
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
        $this->validateUlid($chatSession->ulid);

        $agent = $this->getCurrentAgent();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Only authenticated agents can assign chats'
            ], 403);
        }

        try {
            $this->handle($chatSession, $agent->id, $agent->id);

            return $this->jsonResponse();

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

    public function jsonResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Chat assigned to agent successfully'
        ]);
    }


}
