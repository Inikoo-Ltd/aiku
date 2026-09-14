<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Events\BroadcastMetaChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SetMetaChatSessionPriority
{
    use AsAction;

    public function handle(MetaChatSession $metaChatSession, string $priority, ?ChatAgent $agent = null): MetaChatSession
    {
        $previous = $metaChatSession->priority?->value;

        $metaChatSession->update(['priority' => $priority]);

        StoreMetaChatEvent::make()->handle(
            $metaChatSession,
            ChatEventTypeEnum::PRIORITY,
            ChatActorTypeEnum::AGENT,
            $agent?->id,
            [
                'action_type'       => 'priority',
                'values'            => ['priority' => $priority],
                'priority_previous' => $previous,
                'priority_current'  => $priority,
            ]
        );

        BroadcastMetaChatListEvent::dispatch(null, $metaChatSession);

        return $metaChatSession->fresh();
    }

    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::in(array_column(ChatPriorityEnum::cases(), 'value'))],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, MetaChatSession $metaChatSession, ActionRequest $request): JsonResponse
    {
        $agent = Auth::user()?->chatAgent;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => __('Only authenticated agents can change priority'),
            ], 403);
        }

        try {
            $metaChatSession = $this->handle($metaChatSession, $request->validated()['priority'], $agent);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Priority updated'),
            'data'    => [
                'session_ulid' => $metaChatSession->ulid,
                'priority'     => $metaChatSession->priority->value,
            ],
        ]);
    }
}
