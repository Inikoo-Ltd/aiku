<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Events\BroadcastMetaChatListEvent;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ReleaseMetaChatSession
{
    use AsAction;
    use WithChatAgentAuthorisation;

    /**
     * HELP-4896: giving a WhatsApp conversation back, as ReleaseChatSession does for website chat
     * and email. Its status is left alone: WhatsApp is never stored as waiting, it waits while
     * nobody holds it, so ending the assignment is what puts it back in front of the others.
     */
    public function handle(MetaChatSession $metaChatSession, ChatAgent $agent): void
    {
        DB::transaction(function () use ($metaChatSession, $agent) {
            $metaChatSession->assignments()
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                ->update([
                    'status'      => ChatAssignmentStatusEnum::RESOLVED->value,
                    'resolved_at' => now(),
                    'note'        => 'Released back to the queue',
                ]);

            StoreMetaChatEvent::make()->handle(
                metaChatSession: $metaChatSession,
                eventType: ChatEventTypeEnum::RELEASED,
                actorType: ChatActorTypeEnum::AGENT,
                actorId: $agent->id,
                payload: [
                    'from_agent_id'   => $agent->id,
                    'from_agent_name' => $agent->user?->contact_name,
                    'reason'          => 'agent_released',
                    'timestamp'       => now()->toISOString(),
                ]
            );
        });

        BroadcastMetaChatListEvent::dispatch(null, $metaChatSession->fresh());
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(string $organisation, MetaChatSession $metaChatSession): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($metaChatSession);

        if (!$agent) {
            return response()->json(['success' => false, 'message' => __('Only agents of this shop can release a conversation')], 403);
        }

        if (!$this->userCanDisposeOfChat($agent->user, $metaChatSession)) {
            return response()->json(['success' => false, 'message' => $this->chatHeldByAnotherAgentMessage($metaChatSession)], 403);
        }

        $this->handle($metaChatSession, $agent);

        return response()->json(['success' => true, 'message' => __('Released')]);
    }
}
