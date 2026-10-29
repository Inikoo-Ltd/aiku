<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Events\BroadcastMetaChatListEvent;
use App\Models\Chat\MetaChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\ChatAgent;
use Illuminate\Support\Facades\Auth;

class RestoreMetaChatSession
{
    use AsAction;
    use WithChatAgentAuthorisation;

    /**
     * Brings a trashed thread back. Meta sessions never store `waiting` — it is derived
     * from the absence of an active assignment — so restoring only has to undo the
     * soft delete for the thread to land in the right tab. The contact writing again
     * restores it too, as the guest.
     *
     * @throws \Throwable
     */
    public function handle(MetaChatSession $metaChatSession, ?int $actorId = null, ChatActorTypeEnum $actorType = ChatActorTypeEnum::AGENT): MetaChatSession
    {
        return DB::transaction(function () use ($metaChatSession, $actorId, $actorType) {
            $metaChatSession->restore();

            StoreMetaChatEvent::run(
                $metaChatSession,
                ChatEventTypeEnum::RESTORE,
                $actorType,
                $actorId,
                ['user_id' => Auth::id()]
            );

            BroadcastMetaChatListEvent::dispatch(null, $metaChatSession);

            return $metaChatSession->fresh();
        });
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, MetaChatSession $metaChatSession): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($metaChatSession);

        if (!$agent instanceof ChatAgent) {
            return response()->json([
                'success' => false,
                'message' => __('Only authenticated agents can restore chats'),
            ], 403);
        }

        if (data_get($metaChatSession->metadata, MergeMetaChatSessions::MERGED_INTO_KEY)) {
            return response()->json([
                'success' => false,
                'message' => __('This conversation was merged into another one and cannot be restored'),
            ], 422);
        }

        try {
            $metaChatSession = $this->handle($metaChatSession, $agent->id);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Chat restored'),
            'data'    => ['session_ulid' => $metaChatSession->ulid],
        ]);
    }
}
