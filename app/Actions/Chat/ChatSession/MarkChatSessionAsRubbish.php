<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatIgnoreReasonEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rubbish is what an agent wants out of the way without accusing anybody: an out of office, a
 * newsletter, a supplier's circular. Unlike spam it never blocks the sender, because the same
 * address is a customer writing properly next week. Status and assignment are untouched, so
 * taking the mark off puts the conversation back exactly where it was.
 */
class MarkChatSessionAsRubbish
{
    use AsAction;
    use WithChatAgentAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(
        ChatSession $chatSession,
        ChatAgent $agent,
        bool $rubbish = true,
        ?ChatIgnoreReasonEnum $reason = null
    ): ChatSession {
        return DB::transaction(function () use ($chatSession, $agent, $rubbish, $reason) {
            $chatSession->update([
                'is_rubbish'            => $rubbish,
                'rubbish_at'            => $rubbish ? now() : null,
                'rubbished_by_agent_id' => $rubbish ? $agent->id : null,
                'rubbish_reason'        => $rubbish ? $reason?->value : null,
            ]);

            StoreChatEvent::make()->handle(
                chatSession: $chatSession,
                eventType: $rubbish ? ChatEventTypeEnum::RUBBISH : ChatEventTypeEnum::NOT_RUBBISH,
                actorType: ChatActorTypeEnum::AGENT,
                actorId: $agent->id,
                payload: array_filter([
                    'action_type'    => $rubbish ? 'rubbish' : 'not_rubbish',
                    'marked_by_id'   => $agent->id,
                    'marked_by_name' => $agent->user?->contact_name,
                    'marked_at'      => now()->toISOString(),
                    'reason'         => $rubbish ? $reason?->value : null,
                    'reason_label'   => $rubbish ? $reason?->label() : null,
                ], fn ($value) => $value !== null)
            );

            BroadcastChatListEvent::dispatch(null, $chatSession);

            return $chatSession->fresh();
        });
    }

    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', ChatIgnoreReasonEnum::values())],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $reason = $request->validated('reason');

        return $this->respond($chatSession, true, $reason ? ChatIgnoreReasonEnum::from($reason) : null);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function unmark(?string $organisation, ChatSession $chatSession): JsonResponse
    {
        return $this->respond($chatSession, false);
    }

    private function respond(ChatSession $chatSession, bool $rubbish, ?ChatIgnoreReasonEnum $reason = null): JsonResponse
    {
        $agent = $this->getCurrentAgent($chatSession);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Only authenticated agents can mark chats as rubbish',
            ], 403);
        }

        if ((bool) $chatSession->is_rubbish === $rubbish) {
            return response()->json([
                'success' => false,
                'message' => $rubbish ? 'Chat session is already rubbish' : 'Chat session is not rubbish',
            ], 422);
        }

        try {
            $chatSession = $this->handle($chatSession, $agent, $rubbish, $reason);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $rubbish ? 'Chat marked as rubbish' : 'Chat restored',
            'data'    => [
                'session_ulid'   => $chatSession->ulid,
                'is_rubbish'     => $rubbish,
                'rubbish_reason' => $chatSession->rubbish_reason,
            ],
        ]);
    }

    public function getCurrentAgent(ChatSession $chatSession): ?ChatAgent
    {
        return $this->getAuthorisedChatAgent($chatSession);
    }
}
