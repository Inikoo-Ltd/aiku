<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\Agent\Hydrators\ChatAgentHydrateChats;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAssignment;
use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ReleaseChatSession
{
    use AsAction;
    use WithChatAgentAuthorisation;

    /**
     * Giving a conversation back to whoever can answer it. Opening one takes it, and until now
     * nothing put it down again: an agent who cannot answer — wrong language, not their decision —
     * held it while it looked answered to everybody else.
     */
    public function handle(ChatSession $chatSession, ChatAgent $agent, ?string $reason = null, string $cause = 'agent_released'): void
    {
        $assignment = ChatAssignment::where('chat_session_id', $chatSession->id)
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->first();

        DB::transaction(function () use ($chatSession, $agent, $assignment, $reason, $cause) {
            $assignment?->update([
                'status' => ChatAssignmentStatusEnum::RESOLVED->value,
                'note'   => $reason ?: 'Released back to the queue',
            ]);

            // Waiting is what an unanswered conversation looks like, and what puts it back in
            // front of the other agents. A closed one stays closed: released is not reopened.
            if ($chatSession->status === ChatSessionStatusEnum::ACTIVE) {
                $chatSession->update(['status' => ChatSessionStatusEnum::WAITING->value]);
            }

            StoreChatEvent::make()->handle(
                chatSession: $chatSession,
                eventType: ChatEventTypeEnum::RELEASED,
                actorType: $cause === 'agent_released' ? ChatActorTypeEnum::AGENT : ChatActorTypeEnum::SYSTEM,
                actorId: $cause === 'agent_released' ? $agent->id : null,
                payload: [
                    'from_agent_id'   => $agent->id,
                    'from_agent_name' => $agent->user?->contact_name,
                    'reason'          => $cause,
                    'note'            => $reason ?: null,
                    'timestamp'       => now()->toISOString(),
                ]
            );

            BroadcastChatListEvent::dispatch(null, $chatSession);
        });

        ChatAgentHydrateChats::run($agent);
    }

    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent) {
            return response()->json(['success' => false, 'message' => __('Only agents of this shop can release a conversation')], 403);
        }

        // Only whoever is holding it can put it down, or a supervisor taking it off them. Letting
        // anybody release would let one agent quietly drop the conversation another is answering.
        if (!$this->userCanDisposeOfChat($agent->user, $chatSession)) {
            return response()->json(['success' => false, 'message' => $this->chatHeldByAnotherAgentMessage($chatSession)], 403);
        }

        $this->handle($chatSession, $agent, Arr::get($request->validate($this->rules()), 'reason'));

        return response()->json([
            'success' => true,
            'message' => __('Released'),
            'data'    => ['status' => $chatSession->fresh()->status->value],
        ]);
    }
}
