<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SetChatSessionPriority
{
    use AsAction;

    public function handle(ChatSession $chatSession, string $priority, ?ChatAgent $agent = null): ChatSession
    {
        $previous = $chatSession->priority?->value;

        $chatSession->update(['priority' => $priority]);

        StoreChatEvent::make()->handle(
            chatSession: $chatSession,
            eventType: ChatEventTypeEnum::PRIORITY,
            actorType: ChatActorTypeEnum::AGENT,
            actorId: $agent?->id,
            payload: [
                'action_type'       => 'priority',
                'values'            => ['priority' => $priority],
                'priority_previous' => $previous,
                'priority_current'  => $priority,
            ]
        );

        BroadcastChatListEvent::dispatch(null, $chatSession);

        return $chatSession->fresh();
    }

    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::in(array_column(ChatPriorityEnum::cases(), 'value'))],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        $agent = Auth::user()?->chatAgent;

        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'Only authenticated agents can change priority'], 403);
        }

        try {
            $chatSession = $this->handle($chatSession, $request->validated()['priority'], $agent);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Priority updated',
            'data'    => [
                'session_ulid' => $chatSession->ulid,
                'priority'     => $chatSession->priority->value,
            ],
        ]);
    }
}
