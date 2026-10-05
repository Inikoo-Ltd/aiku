<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 06:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\ChatSession\SendChatAiAnswer;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\MetaChatMessage;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Staff saying a message the chat sent on its own, in either channel, was wrong, and why: the
 * reason is what the messages are corrected from. It stays recorded as sent, only marked. An
 * AI-written answer is marked on its draft, so the inbox can flag any automatic message the same way.
 */
class FlagChatAutomatedMessage
{
    use AsAction;
    use WithChatAgentAuthorisation;

    private const array WHATSAPP_AUTOMATED_KEYS = ['claim_details_asked_at', 'out_of_hours_replied_at', 'asked_if_customer', 'greeted_at', 'greeting'];

    public function handle(ChatMessage|MetaChatMessage $message, int $userId, string $reason): ChatMessage|MetaChatMessage
    {
        if (!Arr::get($message->metadata, 'flagged_wrong_at')) {
            $message->update(['metadata' => array_merge($message->metadata ?? [], [
                'flagged_wrong_at'   => now()->toISOString(),
                'flagged_by_user_id' => $userId,
                'flagged_reason'     => $reason,
            ])]);
        }

        return $message;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function asController(string $channel, int $messageId, ActionRequest $request): void
    {
        $message = $channel === 'whatsapp' ? MetaChatMessage::findOrFail($messageId) : ChatMessage::findOrFail($messageId);
        $user    = $request->user();
        $reason  = $request->validated('reason');

        abort_unless($message->sender_type === ChatSenderTypeEnum::SYSTEM, 404);

        if ($this->isAiAnswer($message, $channel)) {
            $draft = ChatAiDraft::where('reply_message_id', $message->id)
                ->whereNotNull($channel === 'whatsapp' ? 'meta_chat_session_id' : 'chat_session_id')
                ->first();

            abort_unless($draft && FlagChatAiDraft::mayFlag($user, $draft), 404);
            abort_if((bool) $draft->flagged_wrong_at, 422, __('This reply is already marked as wrong'));

            FlagChatAiDraft::make()->handle($draft, $user->id, $reason);
            $this->handle($message, $user->id, $reason);

            return;
        }

        abort_unless($this->isAutomated($message, $channel) && $this->mayFlag($user, $message, $channel), 404);
        abort_if((bool) Arr::get($message->metadata, 'flagged_wrong_at'), 422, __('This reply is already marked as wrong'));

        $this->handle($message, $user->id, $reason);
    }

    private function isAiAnswer(ChatMessage|MetaChatMessage $message, string $channel): bool
    {
        return $channel === 'whatsapp'
            ? (bool) Arr::get($message->metadata, SendChatAiAnswer::SENT_KEY)
            : Arr::get($message->metadata, 'automated') === SendChatAiAnswer::MESSAGE_MARKER;
    }

    private function isAutomated(ChatMessage|MetaChatMessage $message, string $channel): bool
    {
        if ($channel === 'whatsapp') {
            return (bool) Arr::first(
                [...self::WHATSAPP_AUTOMATED_KEYS, 'automated'],
                fn (string $key) => Arr::get($message->metadata, $key)
            );
        }

        return (bool) Arr::get($message->metadata, 'automated');
    }

    private function mayFlag(User $user, ChatMessage|MetaChatMessage $message, string $channel): bool
    {
        $shop = ($channel === 'whatsapp' ? $message->metaChatSession : $message->chatSession)?->shop;

        return $shop
            && $shop->group_id === $user->group_id
            && ($user->hasGroupAccess() || $this->userCanActOnChatOnShop($user, $shop));
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function jsonResponse(): JsonResponse
    {
        return response()->json(['success' => true]);
    }
}
