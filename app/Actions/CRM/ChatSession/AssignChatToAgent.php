<?php

namespace App\Actions\CRM\ChatSession;

use Exception;
use Illuminate\Http\JsonResponse;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatEvent;
use Illuminate\Http\Request;
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


      public function handle(ChatSession $chatSession, int $agentId, int $assignedByAgentId, ?string $transferReason = null): ChatAssignment
    {
        $agent = ChatAgent::findOrFail($agentId);

        $isSelfAssign = $agentId === $assignedByAgentId;

        $previousAssignment = ChatAssignment::where('chat_session_id', $chatSession->id)
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->first();

        $isTransfer = $previousAssignment && $previousAssignment->chat_agent_id !== $agentId;

        if (!$isSelfAssign &&
            (!$previousAssignment || $previousAssignment->chat_agent_id !== $agentId) &&
            !$agent->isAvailableForChat()) {
            throw new Exception('Agent is not available for new chats.');
        }

        $previousStatus = $chatSession->status?->value;
        $chatSession->update([
            'status' => ChatSessionStatusEnum::ACTIVE->value
        ]);

        ChatAssignment::where('chat_session_id', $chatSession->id)
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->update([
                'status' => ChatAssignmentStatusEnum::RESOLVED->value,
                'resolved_at' => now()
            ]);

        $chatAssignment = ChatAssignment::create([
            'chat_session_id' => $chatSession->id,
            'chat_agent_id' => $agentId,
            'status' => ChatAssignmentStatusEnum::ACTIVE->value,
            'assigned_by' => ChatAssignmentAssignedByEnum::AGENT->value,
            'note' => $transferReason,
            'assigned_at' => now(),
        ]);

        if (!$isSelfAssign) {
            $agent->incrementChatCount();

            if ($previousAssignment && $previousAssignment->chat_agent_id !== $agentId) {
                $previousAgent = ChatAgent::find($previousAssignment->chat_agent_id);
                if ($previousAgent) {
                    $previousAgent->decrementChatCount();
                }
            }
        }

        $this->logChatEvent($chatSession, $assignedByAgentId, $agentId, $previousStatus, $isTransfer, $isSelfAssign, $transferReason);

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
            'note' => [
                'nullable',
                'string',
                'max:100'
            ],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'agent_id.required' => 'Agent ID is required',
            'agent_id.integer' => 'Agent ID must be an integer',
            'agent_id.exists' => 'Agent not found',
            'note.string' => 'Transfer reason must be a string',
            'note.max' => 'Transfer reason must not exceed 500 characters',
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
            $chatAssignment = $this->handle(
                $chatSession,
                $validated['agent_id'],
                $assignedByAgent->id,
                $validated['note'] ?? null
            );

            $actionType = $this->getActionType($assignedByAgent->id, $validated['agent_id'], $chatSession);

            return response()->json([
                'success' => true,
                'message' => $this->getSuccessMessage($actionType),
                'data' => [
                    'assignment_id' => $chatAssignment->id,
                    'session_ulid' => $chatSession->ulid,
                    'session_status' => $chatSession->status->value,
                    'assigned_agent_id' => $validated['agent_id'],
                    'assigned_agent_name' => $chatAssignment->chatAgent->user->contact_name ?? 'Unknown',
                    'assigned_by_agent_id' => $assignedByAgent->id,
                    'action_type' => $actionType,
                    'note' => $validated['note'] ?? null,
                ]
            ]);

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

        return ChatAgent::where('user_id', 177)->first();
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
            $chatAssignment = $this->handle($chatSession, $agent->id, $agent->id, null);

            return response()->json([
                'success' => true,
                'message' => 'Chat assigned to you successfully',
                'data' => [
                    'assignment_id' => $chatAssignment->id,
                    'session_ulid' => $chatSession->ulid,
                    'session_status' => $chatSession->status->value,
                    'assigned_agent_id' => $agent->id,
                    'assigned_agent_name' => $agent->user->name ?? 'Unknown',
                    'action_type' => 'self_assign',
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    protected function logChatEvent(
        ChatSession $chatSession,
        int $fromAgentId,
        int $toAgentId,
        string $previousStatus,
        bool $isTransfer,
        bool $isSelfAssign,
        ?string $transferReason = null
    ): void {

        $eventAction = app(StoreChatEvent::class);

        $payload = [
            'from_agent_id' => $fromAgentId,
            'to_agent_id' => $toAgentId,
            'session_previous_status' => $previousStatus,
            'session_new_status' => ChatSessionStatusEnum::ACTIVE->value,
            'timestamp' => now()->toISOString(),
        ];

        if ($transferReason) {
            $payload['note'] = $transferReason;
        }


        if ($isSelfAssign) {
            $eventAction->handle(
                chatSession: $chatSession,
                eventType: ChatEventTypeEnum::ASSIGNMENT_TO_SELF,
                actorType: ChatActorTypeEnum::AGENT,
                actorId: $fromAgentId,
                payload: [
                    ...$payload,
                    'action_type' => 'assignment_to_self',
                ]
            );
            return;
        }


        if ($isTransfer) {
            $eventAction->handle(
                chatSession: $chatSession,
                eventType: ChatEventTypeEnum::TRANSFER_TO_AGENT,
                actorType: ChatActorTypeEnum::AGENT,
                actorId: $fromAgentId,
                payload: [
                    ...$payload,
                    'action_type' => 'transfer_to_agent',
                    'transfer_reason' => $transferReason,
                ]
            );
            return;
        }

        $eventAction->handle(
            chatSession: $chatSession,
            eventType: ChatEventTypeEnum::TRANSFER_TO_AGENT,
            actorType: ChatActorTypeEnum::AGENT,
            actorId: $fromAgentId,
            payload: [
                ...$payload,
                'action_type' => 'assign_to_other_agent'
            ]
        );
    }

    protected function getActionType(int $assignedByAgentId, int $targetAgentId, ChatSession $chatSession): string
    {
        if ($assignedByAgentId === $targetAgentId) {
            return 'self_assign';
        }

        $previousAssignment = ChatAssignment::where('chat_session_id', $chatSession->id)
            ->where('status', ChatAssignmentStatusEnum::RESOLVED->value)
            ->latest()
            ->first();

        if ($previousAssignment && $previousAssignment->chat_agent_id !== $targetAgentId) {
            return 'transfer';
        }

        return 'direct_assign';
    }

    protected function getSuccessMessage(string $actionType): string
    {
        return match($actionType) {
            'self_assign' => 'Chat assigned to you successfully',
            'transfer' => 'Chat transferred successfully',
            'direct_assign' => 'Chat assigned to agent successfully',
            default => 'Chat assignment completed successfully'
        };
    }


}
