<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use App\Actions\Helpers\Translations\DetectLanguageWithJev;
use App\Actions\Helpers\Translations\Translate;
use App\Models\Chat\ChatAiDraft;
use App\Models\Helpers\Language;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the inbox does with a draft: read the one waiting, take it into the composer, throw it
 * away, or give it stars. Throwing it away, or rating it a wrong fact, stops trusting the verified
 * facts it was built on. Staff agents on the conversation only: the draft carries a customer's order details
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

    public function take(ChatAiDraft $chatAiDraft): JsonResponse
    {
        return $this->decide($chatAiDraft, fn () => $chatAiDraft->update(['taken_at' => $chatAiDraft->taken_at ?? now()]));
    }

    public function discard(ChatAiDraft $chatAiDraft): JsonResponse
    {
        return $this->decide($chatAiDraft, function (int $userId) use ($chatAiDraft) {
            $chatAiDraft->update([
                'status'             => ChatAiDraftStatusEnum::DISCARDED,
                'decided_by_user_id' => $userId,
                'decided_at'         => now(),
            ]);
            RememberVerifiedChatFact::disputeFactsOf($chatAiDraft, 'Staff discarded a draft built on it', $userId);
        });
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

        if ($chatAiDraft->rating_reason === 'wrong_fact') {
            RememberVerifiedChatFact::disputeFactsOf($chatAiDraft, 'Staff rated a draft built on it as a wrong fact', $agent->user_id);
        }

        return response()->json(['success' => true]);
    }

    private function show(ChatSession|MetaChatSession $chatSession): JsonResponse
    {
        if (!$this->getAuthorisedChatAgent($chatSession)) {
            return response()->json(['success' => false], 403);
        }

        $draft = DraftChatReply::pendingDraft($chatSession);

        if (data_get($draft?->facts, 'mode') !== DraftChatReply::ASSISTANT) {
            $draft = null;
        }

        return response()->json(['data' => $draft ? [
            'id'          => $draft->id,
            'text'        => $draft->text,
            'topic'       => $draft->topic->value,
            'topic_label' => $draft->topic->label(),
            'assistant'   => array_filter([
                'of'     => User::find(data_get($draft->facts, 'user_id'))?->contact_name,
                'client' => data_get($draft->facts, 'client'),
            ]),
            'staff_note'  => data_get($draft->facts, 'staff_note'),
            'checked'     => data_get($draft->facts, 'checked', []),
            'premonitions' => Ticket::whereIn('reference', data_get($draft->facts, 'premonitions', []))
                ->where('type', TicketTypeEnum::PREMONITION)
                ->get(['reference', 'subject', 'status'])
                ->map(fn (Ticket $ticket) => [
                    'reference' => $ticket->reference,
                    'subject'   => $ticket->subject,
                    'fixed'     => $ticket->status === TicketStatusEnum::RESOLVED,
                    'url'       => route('grp.tickets.show', $ticket->reference),
                ])->all(),
            'actions'     => $chatSession instanceof ChatSession ? RunChatAiDraftAction::views($chatSession, $draft) : [],
            'questions'        => data_get($draft->facts, 'questions', []),
            'since_last_reply' => data_get($draft->facts, 'since_last_reply'),
            'other_chats'      => $chatSession instanceof ChatSession && data_get($draft->facts, 'questions') ? self::otherOpenChats($chatSession) : [],
            'rating'      => $draft->rating,
            'reason'      => $draft->rating_reason,
        ] : null]);
    }

    /**
     * The same customer still waiting in another chat, in any shop of the group: another inbox, or
     * the website as well as email. Found by login, by customer, or by email address.
     *
     * @return list<array{ulid: string, shop: string|null, channel: string|null, url: string}>
     */
    public static function otherOpenChats(ChatSession $chatSession): array
    {
        $webUser = $chatSession->webUser;
        $emails  = array_values(array_unique(array_filter(array_map(
            fn (?string $email) => $email ? strtolower(trim($email)) : null,
            [data_get($chatSession->metadata, 'email'), $webUser?->email, $webUser?->customer?->email]
        ))));

        if (!$webUser && !$emails) {
            return [];
        }

        return ChatSession::with('shop.organisation')
            ->where('id', '!=', $chatSession->id)
            ->whereNotIn('status', [ChatSessionStatusEnum::CLOSED, ChatSessionStatusEnum::RESOLVED])
            ->where('is_spam', false)
            ->whereHas('shop', fn ($query) => $query->where('group_id', $chatSession->shop->group_id))
            ->where(fn ($query) => $query
                ->when($webUser, fn ($query) => $query
                    ->where('web_user_id', $webUser->id)
                    ->orWhereHas('webUser', fn ($query) => $query->where('customer_id', $webUser->customer_id)))
                ->when($emails, fn ($query) => $query->orWhereIn(DB::raw("lower(metadata->>'email')"), $emails)))
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (ChatSession $other) => [
                'ulid'    => $other->ulid,
                'shop'    => $other->shop?->name,
                'channel' => $other->channel?->value,
                'url'     => route('grp.org.chat.inbox.conversation', [$other->shop->organisation->slug, $other->ulid]),
            ])->all();
    }

    /**
     * The assistant writes its notes for staff in the organisation's language; a colleague who reads
     * another one asks for them in their own, one click on the draft, and gets the note, what was
     * checked and the task buttons translated. The customer's reply is never touched.
     */
    public function translateForStaff(ChatAiDraft $chatAiDraft, Request $request): JsonResponse
    {
        $session = $chatAiDraft->session();

        if (!$session || !$this->getAuthorisedChatAgent($session)) {
            return response()->json(['success' => false], 403);
        }

        $to       = Language::find($request->user()->language_id) ?? Language::where('code', 'en')->firstOrFail();
        $note     = (string) data_get($chatAiDraft->facts, 'staff_note', '');
        $checked  = data_get($chatAiDraft->facts, 'checked', []);
        $labels   = collect(data_get($chatAiDraft->facts, 'actions', []))->filter(fn (array $action) => isset($action['label']))->map(fn (array $action) => $action['label']);
        $questions = array_column(data_get($chatAiDraft->facts, 'questions', []), 'text');
        $from     = DetectLanguageWithJev::run(trim($note.' '.implode(' ', $checked).' '.$labels->implode(' ').' '.implode(' ', $questions))) ?? Language::where('code', 'en')->firstOrFail();
        $translate = fn (string $text) => Translate::run($text, $from, $to);

        return response()->json(['success' => true, 'data' => [
            'language'   => $to->name,
            'staff_note' => $note !== '' ? $translate($note) : null,
            'checked'    => array_map($translate, $checked),
            'labels'     => $labels->map($translate)->all(),
            'questions'  => array_map($translate, $questions),
        ]]);
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
