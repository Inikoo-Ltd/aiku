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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the inbox does with a draft: read the one waiting, take it into the composer, throw it
 * away, or give it stars. Staff agents on the conversation only: the draft carries a customer's order details
 * and must never reach the widget, which reads the same session endpoints without logging in.
 */
class HandleChatAiDraft
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public const array RATING_REASONS = ['missing_info', 'wrong_fact', 'too_long', 'no_reply_needed'];

    public function asController(ChatSession $chatSession): JsonResponse
    {
        return $this->show($chatSession);
    }

    public function inMetaChatSession(MetaChatSession $metaChatSession): JsonResponse
    {
        return $this->show($metaChatSession);
    }

    public function suggestionUsed(ChatSession $chatSession, Request $request): JsonResponse
    {
        return $this->recordUse($chatSession, $request);
    }

    public function suggestionUsedInMetaChatSession(MetaChatSession $metaChatSession, Request $request): JsonResponse
    {
        return $this->recordUse($metaChatSession, $request);
    }

    private function recordUse(ChatSession|MetaChatSession $chatSession, Request $request): JsonResponse
    {
        if (!$this->getAuthorisedChatAgent($chatSession)) {
            return response()->json(['success' => false], 403);
        }

        $validated = $request->validate([
            'kind'       => ['required', 'in:guide,close,closing_message,wait'],
            'reading_id' => ['nullable', 'integer'],
        ]);

        ClassifyChatTurn::markUsed($chatSession, $validated['reading_id'] ?? null, $validated['kind']);

        return response()->json(['success' => true]);
    }

    public function goodbye(ChatSession $chatSession): JsonResponse
    {
        return $this->writeGoodbye($chatSession);
    }

    public function goodbyeInMetaChatSession(MetaChatSession $metaChatSession): JsonResponse
    {
        return $this->writeGoodbye($metaChatSession);
    }

    /**
     * The goodbye is written only when an agent asks for it: most conversations that end with a
     * thanks close on their own, and writing one for each cost three model calls for nothing.
     */
    private function writeGoodbye(ChatSession|MetaChatSession $chatSession): JsonResponse
    {
        if (!$this->getAuthorisedChatAgent($chatSession)) {
            return response()->json(['success' => false], 403);
        }

        $suggestions = ClassifyChatTurn::suggestions($chatSession);

        if (($suggestions['next_step']['kind'] ?? null) !== 'close') {
            return response()->json(['success' => false, 'message' => __('The customer is not ending the conversation')], 422);
        }

        $message = Cache::remember(
            'chat-goodbye:'.class_basename($chatSession).':'.$chatSession->id.':'.data_get($chatSession->metadata, ClassifyChatTurn::KEY.'.at'),
            now()->addDay(),
            fn () => ClassifyChatTurn::closingMessage($chatSession, ClassifyChatTurn::customerWrote($chatSession), ClassifyChatTurn::weSaid($chatSession))
        );

        if (!$message) {
            return response()->json(['success' => false, 'message' => __('No goodbye could be written, please write your own')], 422);
        }

        ClassifyChatTurn::markUsed($chatSession, $suggestions['reading_id'] ?? null, 'closing_message');

        return response()->json(['success' => true, 'data' => ['message' => $message]]);
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

    /**
     * One click from the agent, 1 to 5 stars, at any time while the draft is on screen; a
     * second click changes it. A low rating may say why, which tells us what to fix next.
     */
    public function rate(ChatAiDraft $chatAiDraft, Request $request): JsonResponse
    {
        $session = $chatAiDraft->session();
        $agent   = $session ? $this->getAuthorisedChatAgent($session) : null;

        if (!$agent) {
            return response()->json(['success' => false], 403);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'reason' => ['nullable', 'in:'.implode(',', self::RATING_REASONS)],
        ]);

        $chatAiDraft->update([
            'rating'           => $validated['rating'],
            'rating_reason'    => $validated['rating'] <= 3 ? ($validated['reason'] ?? null) : null,
            'rated_by_user_id' => $agent->user_id,
            'rated_at'         => now(),
        ]);

        return response()->json(['success' => true]);
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
            'rating'      => $draft->rating,
            'reason'      => $draft->rating_reason,
        ] : null, 'suggestions' => ClassifyChatTurn::suggestions($chatSession)]);
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
