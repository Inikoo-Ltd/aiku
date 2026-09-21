<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkChatSessionAsSpam
{
    use AsAction;
    use WithChatAgentAuthorisation;

    /**
     *
     * @throws \Throwable
     */
    public function handle(ChatSession $chatSession, ChatAgent $agent): ChatSession
    {
        return DB::transaction(function () use ($chatSession, $agent) {
            // Only hide the chat (is_spam). Status and any assignment are left intact
            // so un-marking restores the exact previous state (waiting/active/closed).
            $chatSession->update([
                'is_spam'             => true,
                'spam_at'             => now(),
                'spammed_by_agent_id' => $agent->id,
            ]);

            if ($chatSession->channel === ChatChannelEnum::EMAIL) {
                $email = Arr::get($chatSession->metadata, 'email');
                if ($email) {
                    $shop = $chatSession->shop;
                    if ($shop) {
                        $settings = $shop->settings ?? [];
                        $blocked = Arr::get($settings, 'gmail.blocked_senders', []);
                        $blocked[] = strtolower($email);
                        Arr::set($settings, 'gmail.blocked_senders', array_values(array_unique($blocked)));
                        $shop->update(['settings' => $settings]);
                    }
                }
            }

            ClassifyChatSessionNoise::humanDecided($chatSession, true);


            StoreChatEvent::make()->handle(
                chatSession: $chatSession,
                eventType: ChatEventTypeEnum::SPAM,
                actorType: ChatActorTypeEnum::AGENT,
                actorId: $agent->id,
                payload: [
                    'action_type'     => 'spam',
                    'spammed_by_id'   => $agent->id,
                    'spammed_by_name' => $agent->user?->contact_name,
                    'spammed_at'      => now()->toISOString(),
                ]
            );

            BroadcastChatListEvent::dispatch(null, $chatSession);

            return $chatSession->fresh();
        });
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession): JsonResponse
    {
        $agent = $this->getCurrentAgent($chatSession);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Only authenticated agents can mark chats as spam',
            ], 403);
        }

        if ($chatSession->is_spam) {
            return response()->json([
                'success' => false,
                'message' => 'Chat session is already marked as spam',
            ], 422);
        }

        try {
            $chatSession = $this->handle($chatSession, $agent);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chat marked as spam',
            'data'    => [
                'session_ulid' => $chatSession->ulid,
                'is_spam'      => true,
                'action_type'  => 'spam',
            ],
        ]);
    }

    public function getCurrentAgent(ChatSession $chatSession): ?ChatAgent
    {
        return $this->getAuthorisedChatAgent($chatSession);
    }
}
