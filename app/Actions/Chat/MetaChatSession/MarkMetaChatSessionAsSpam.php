<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Events\BroadcastMetaChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkMetaChatSessionAsSpam
{
    use AsAction;

    /**
     * Only hides the thread. Status and assignment are left intact so un-marking
     * restores the exact previous state.
     *
     * @throws \Throwable
     */
    public function handle(MetaChatSession $metaChatSession, ChatAgent $agent): MetaChatSession
    {
        return DB::transaction(function () use ($metaChatSession, $agent) {
            $metaChatSession->update([
                'is_spam'             => true,
                'spam_at'             => now(),
                'spammed_by_agent_id' => $agent->id,
            ]);

            StoreMetaChatEvent::make()->handle(
                $metaChatSession,
                ChatEventTypeEnum::SPAM,
                ChatActorTypeEnum::AGENT,
                $agent->id,
                [
                    'action_type'     => 'spam',
                    'spammed_by_id'   => $agent->id,
                    'spammed_by_name' => $agent->user?->contact_name,
                    'spammed_at'      => now()->toISOString(),
                ]
            );

            BroadcastMetaChatListEvent::dispatch(null, $metaChatSession);

            return $metaChatSession->fresh();
        });
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, MetaChatSession $metaChatSession): JsonResponse
    {
        $agent = Auth::user()?->chatAgent;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => __('Only authenticated agents can mark chats as spam'),
            ], 403);
        }

        if ($metaChatSession->is_spam) {
            return response()->json([
                'success' => false,
                'message' => __('Chat session is already marked as spam'),
            ], 422);
        }

        try {
            $metaChatSession = $this->handle($metaChatSession, $agent);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Chat marked as spam'),
            'data'    => [
                'session_ulid' => $metaChatSession->ulid,
                'is_spam'      => true,
                'action_type'  => 'spam',
            ],
        ]);
    }
}
