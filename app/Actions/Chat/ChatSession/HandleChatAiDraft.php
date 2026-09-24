<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the inbox does with a draft: read the one waiting, take it into the composer, or throw
 * it away. Staff agents on the conversation only: the draft carries a customer's order details
 * and must never reach the widget, which reads the same session endpoints without logging in.
 */
class HandleChatAiDraft
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function asController(ChatSession $chatSession): JsonResponse
    {
        return $this->show($chatSession);
    }

    public function inMetaChatSession(MetaChatSession $metaChatSession): JsonResponse
    {
        return $this->show($metaChatSession);
    }

    public function take(ChatAiDraft $chatAiDraft): JsonResponse
    {
        return $this->decide($chatAiDraft, fn () => $chatAiDraft->update(['taken_at' => $chatAiDraft->taken_at ?? now()]));
    }

    public function discard(ChatAiDraft $chatAiDraft): JsonResponse
    {
        return $this->decide($chatAiDraft, fn (int $userId) => $chatAiDraft->update([
            'status'             => ChatAiDraftStatusEnum::DISCARDED,
            'decided_by_user_id' => $userId,
            'decided_at'         => now(),
        ]));
    }

    private function show(ChatSession|MetaChatSession $chatSession): JsonResponse
    {
        if (!$this->getAuthorisedChatAgent($chatSession)) {
            return response()->json(['success' => false], 403);
        }

        $draft = DraftChatReply::pendingDraft($chatSession);

        return response()->json(['data' => $draft ? [
            'id'          => $draft->id,
            'text'        => $draft->text,
            'topic'       => $draft->topic->value,
            'topic_label' => $draft->topic->label(),
        ] : null]);
    }

    private function decide(ChatAiDraft $chatAiDraft, callable $change): JsonResponse
    {
        $session = $chatAiDraft->session();
        $agent   = $session ? $this->getAuthorisedChatAgent($session) : null;

        if (!$agent) {
            return response()->json(['success' => false], 403);
        }

        if ($chatAiDraft->status !== ChatAiDraftStatusEnum::PENDING) {
            return response()->json(['success' => false, 'message' => __('This draft is no longer waiting')], 422);
        }

        $change($agent->user_id);

        return response()->json(['success' => true, 'data' => ['text' => $chatAiDraft->text]]);
    }
}
