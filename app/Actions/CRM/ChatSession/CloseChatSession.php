<?php

namespace App\Actions\CRM\ChatSession;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Events\BroadcastRealtimeChat;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use Illuminate\Validation\ValidationException;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionClosedByTypeEnum;

class CloseChatSession
{
    use AsAction;

    public function handle(ChatSession $chatSession, int $agentId, array $additionalData = []): ChatSession
    {
        return DB::transaction(function () use ($chatSession, $agentId, $additionalData) {

            $chatSession->update([
                'status' => ChatSessionStatusEnum::CLOSED->value,
                'closed_by' => $agentId ? ChatSessionClosedByTypeEnum::AGENT->value : ChatSessionClosedByTypeEnum::USER->value,
                'closed_at' => now(),
            ]);

            $activeAssignments = $chatSession->assignments()
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                ->get();

            foreach ($activeAssignments as $assignment) {
                $assignment->update([
                    'status' => ChatAssignmentStatusEnum::RESOLVED->value,
                    'resolved_at' => now(),
                ]);

                $agent = ChatAgent::find($assignment->chat_agent_id);
                if ($agent) {
                    $agent->decrementChatCount();
                }
            }

            $systemMessage = $chatSession->messages()->create([
                'message_text' => 'Chat session has been closed by agent',
                'message_type' => ChatMessageTypeEnum::TEXT->value,
                'sender_type' => ChatSenderTypeEnum::SYSTEM->value,
                'is_read' => true,
                'read_at' => now(),
                'delivered_at' => now(),
            ]);

            BroadcastRealtimeChat::dispatch($systemMessage);

            $this->logCloseEvent($chatSession, $agentId, $activeAssignments, $additionalData);

            return $chatSession->fresh();
        });
    }


    public function asController(ActionRequest $request, ?string $organisation, ChatSession $chatSession): RedirectResponse
    {
        $agent = $this->getCurrentAgent();
        if (!$agent) {
            throw ValidationException::withMessages([
                'message' => 'User not found',
            ]);
        }

        try {
            $this->handle($chatSession, $agent->id);
        } catch (Exception $e) {
            throw ValidationException::withMessages([
                'message' => $e->getMessage(),
            ]);
        }

        return back()->setStatusCode(303);
    }

    public function getCurrentAgent(): ?ChatAgent
    {
        $user = Auth::user();

        if ($user) {
            if (!$user->chatAgent) {
                return null;
            }
        }
        return $user->chatAgent;
    }

    protected function logCloseEvent(ChatSession $chatSession, int $agentId, $assignments, array $additionalData = []): void
    {

        $payload = [];
        if ($assignments->isNotEmpty()) {
            $payload['assignments'] = $assignments->map(function ($assignment) {
                return [
                    'assignment_id' => $assignment->id,
                    'assigned_agent_id' => $assignment->chat_agent_id,
                    'assigned_at' => $assignment->assigned_at->toISOString(),
                    'assignment_duration' => $assignment->assigned_at->diffInMinutes(now()),
                ];
            })->toArray();
        }

        $payload = array_merge($payload, $additionalData);

        StoreChatEvent::make()->closeSession(
            $chatSession,
            ChatActorTypeEnum::AGENT,
            $agentId,
            $payload,
        );
    }

    public function jsonResponse(ChatSession $chatSession): JsonResponse
    {
        $sessionDuration = $chatSession->created_at->diffInMinutes($chatSession->closed_at);

        $resolvedAssignments = $chatSession->assignments()
            ->where('status', ChatAssignmentStatusEnum::RESOLVED->value)
            ->get();

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
                'assignments' => $resolvedAssignments->map(function ($assignment) {
                    return [
                        'resolved' => true,
                        'assignment_id' => $assignment->id,
                        'resolved_at' => $assignment->resolved_at->toISOString(),
                    ];
                }),
            ],
        ]);
    }
}
