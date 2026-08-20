<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ReopenMetaChatSession
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(MetaChatSession $metaChatSession, ChatAgent $agent): MetaChatSession
    {
        return DB::transaction(function () use ($metaChatSession, $agent) {
            $previousStatus = $metaChatSession->status?->value;

            $metaChatSession->update([
                'status'    => ChatSessionStatusEnum::ACTIVE->value,
                'closed_by' => null,
                'closed_at' => null,
            ]);

            $assignment = AssignMetaChatToAgent::run($metaChatSession, $agent, 'Reopened by agent');

            // ponytail: no meta broadcast exists yet; dispatch here when one lands
            StoreMetaChatMessage::run($metaChatSession, [
                'message_text' => 'Chat session has been reopened by '.($agent->user?->contact_name ?? 'agent'),
                'message_type' => ChatMessageTypeEnum::TEXT->value,
                'sender_type'  => ChatSenderTypeEnum::SYSTEM->value,
                'is_read'      => true,
                'read_at'      => now(),
                'delivered_at' => now(),
            ]);

            StoreMetaChatEvent::make()->reopenSession(
                $metaChatSession,
                ChatActorTypeEnum::AGENT,
                $agent->id,
                [
                    'action_type'             => 'reopen',
                    'assignment_id'           => $assignment->id,
                    'assigned_agent_id'       => $agent->id,
                    'assigned_agent_name'     => $agent->user?->contact_name,
                    'session_previous_status' => $previousStatus,
                    'session_new_status'      => ChatSessionStatusEnum::ACTIVE->value,
                ]
            );

            return $metaChatSession->fresh();
        });
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(string $organisation, MetaChatSession $metaChatSession): JsonResponse
    {
        $agent = $this->getCurrentAgent();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => __('Only authenticated agents can reopen chats'),
            ], 403);
        }

        if ($metaChatSession->status !== ChatSessionStatusEnum::CLOSED) {
            return response()->json([
                'success' => false,
                'message' => __('Chat session is not closed'),
            ], 422);
        }

        try {
            $metaChatSession = $this->handle($metaChatSession, $agent);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Chat reopened successfully'),
            'data'    => [
                'session_ulid'        => $metaChatSession->ulid,
                'session_status'      => $metaChatSession->status->value,
                'assigned_agent_id'   => $agent->id,
                'assigned_agent_name' => $agent->user?->contact_name ?? 'Unknown',
                'action_type'         => 'reopen',
            ],
        ]);
    }

    public function getCurrentAgent(): ?ChatAgent
    {
        return Auth::user()?->chatAgent;
    }
}
