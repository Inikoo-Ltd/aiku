<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastMetaChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TrashMetaChatSession
{
    use AsAction;

    /**
     * Soft-deletes the thread. A chat an agent is actually handling is closed first so
     * it comes back in a clean, ended state; an unclaimed one is trashed as-is and
     * returns to the waiting queue when restored.
     *
     * Trashing does not stop the customer: a new WhatsApp message revives the thread,
     * which is the behaviour we want because the number keeps writing to us either way.
     *
     * @throws \Throwable
     */
    public function handle(MetaChatSession $metaChatSession, ?int $actorId = null): MetaChatSession
    {
        return DB::transaction(function () use ($metaChatSession, $actorId) {
            $hasActiveAgent = $metaChatSession->assignments()
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                ->exists();

            if ($hasActiveAgent && $metaChatSession->status !== ChatSessionStatusEnum::CLOSED) {
                CloseMetaChatSession::run($metaChatSession, $actorId, ChatActorTypeEnum::AGENT);
            }

            BroadcastMetaChatListEvent::dispatch(null, $metaChatSession);

            $metaChatSession->delete();

            return $metaChatSession;
        });
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, MetaChatSession $metaChatSession): JsonResponse
    {
        $agent = Auth::user()?->chatAgent;

        if (!$agent instanceof ChatAgent) {
            return response()->json([
                'success' => false,
                'message' => __('Only authenticated agents can trash chats'),
            ], 403);
        }

        try {
            $metaChatSession = $this->handle($metaChatSession, $agent->id);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Chat moved to trash'),
            'data'    => ['session_ulid' => $metaChatSession->ulid],
        ]);
    }
}
