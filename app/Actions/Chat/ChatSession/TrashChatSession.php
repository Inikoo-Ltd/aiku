<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\Agent\Hydrators\ChatAgentHydrateChats;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class TrashChatSession
{
    use AsAction;

    /**
     * Soft-delete the session (moves it to Trash). SoftDeletes hides it from every
     * normal query; it can be restored or permanently deleted from the Trash view.
     *
     * @throws \Throwable
     */
    public function handle(ChatSession $chatSession): ChatSession
    {
        return DB::transaction(function () use ($chatSession) {
            $activeAssignments = $chatSession->assignments()
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                ->get();

            foreach ($activeAssignments as $assignment) {
                $assignment->update([
                    'status'      => ChatAssignmentStatusEnum::RESOLVED->value,
                    'resolved_at' => now(),
                ]);

                $agent = ChatAgent::find($assignment->chat_agent_id);
                if ($agent) {
                    ChatAgentHydrateChats::run($agent);
                }
            }

            BroadcastChatListEvent::dispatch(null, $chatSession);

            $chatSession->delete();

            return $chatSession;
        });
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        if (!Auth::user()?->chatAgent) {
            return response()->json(['success' => false, 'message' => 'Only authenticated agents can trash chats'], 403);
        }

        try {
            $chatSession = $this->handle($chatSession);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chat moved to trash',
            'data'    => ['session_ulid' => $chatSession->ulid],
        ]);
    }
}
