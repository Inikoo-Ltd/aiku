<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Http\Resources\CRM\Livechat\ChatTimelineEventResource;
use App\Actions\Chat\Reports\GetChatAiAcceptance;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatAiMoodEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use App\Actions\Helpers\Translations\DetectLanguageWithJev;
use App\Actions\Helpers\Translations\Translate;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\MetaChatMessage;
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
 * away. Throwing it away stops trusting the verified facts it was built on only once the agent's
 * reply says something else (SettleChatAiDraft), since
 * staff often discard to clear the box and then type the same answer. Staff agents on the conversation only: the draft carries a customer's order details
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

    /**
     * Staff selected and copied the draft text instead of pressing Use: recorded once, quietly.
     */
    public function copied(ChatAiDraft $chatAiDraft): JsonResponse
    {
        return $this->decide($chatAiDraft, fn () => $chatAiDraft->copied_at || $chatAiDraft->update(['copied_at' => now()]));
    }

    public function discard(ChatAiDraft $chatAiDraft): JsonResponse
    {
        return $this->decide($chatAiDraft, function (int $userId) use ($chatAiDraft) {
            $chatAiDraft->update([
                'status'             => ChatAiDraftStatusEnum::DISCARDED,
                'decided_by_user_id' => $userId,
                'decided_at'         => now(),
            ]);
            MeasureChatAiDraft::run($chatAiDraft);
        });
    }

    private function show(ChatSession|MetaChatSession $chatSession): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent) {
            return response()->json(['success' => false], 403);
        }

        $draft = DraftChatReply::pendingDraft($chatSession);

        if (data_get($draft?->facts, 'mode') !== DraftChatReply::ASSISTANT || self::writtenBeforeCustomersLatestMessage($chatSession, $draft)) {
            $draft = null;
        }

        return response()->json(['data' => $draft ? [
            'id'          => $draft->id,
            'text'        => $draft->text,
            'topic'       => $draft->topic->value,
            'topic_label' => $draft->topic->label(),
            'mood'        => $draft->mood ? ['value' => $draft->mood->value, 'label' => $draft->mood->label(), 'tone' => $draft->mood->tone(), 'usual' => self::usualMood($chatSession, $draft)] : null,
            'assistant'   => array_filter([
                'of'     => User::find(data_get($draft->facts, 'user_id'))?->contact_name,
                'client' => data_get($draft->facts, 'client'),
            ]),
            'staff_note'  => data_get($draft->facts, 'staff_note'),
            'checked'     => data_get($draft->facts, 'checked', []),
            'premonitions' => Ticket::whereIn('reference', data_get($draft->facts, 'premonitions', []))
                ->where('type', TicketTypeEnum::PREMONITION)
                ->with('assignee.image')
                ->get()
                ->map(fn (Ticket $ticket) => [...ChatTimelineEventResource::ticketChip($ticket), 'fixed' => $ticket->status === TicketStatusEnum::RESOLVED])
                ->all(),
            'actions'     => $chatSession instanceof ChatSession ? RunChatAiDraftAction::views($chatSession, $draft) : [],
            'questions'        => data_get($draft->facts, 'questions', []),
            'since_last_reply' => data_get($draft->facts, 'since_last_reply'),
            'other_chats'      => $chatSession instanceof ChatSession && data_get($draft->facts, 'questions') ? self::otherOpenChats($chatSession) : [],
            'aura_angry'  => GetChatAiAcceptance::make()->isAngryWith($agent->user_id),
            'translate_for_me' => self::isInAnotherLanguage($chatSession, $draft, self::staffLanguageId($agent, request()->user()))
                && !self::reads(request()->user(), self::replyLanguageId($chatSession, $draft)),
            'notes_for_me'     => self::notesInAnotherLanguage($chatSession, $draft, self::staffLanguageId($agent, request()->user()))
                && !self::reads(request()->user(), $chatSession->shop?->organisation?->language_id),
            'edit_in_my_language' => self::editsInOwnLanguage($agent, self::replyLanguageId($chatSession, $draft)),
            'my_language'      => Language::find(self::staffLanguageId($agent, request()->user()) ?? 0)?->name,
            'customer_language' => Language::find(self::replyLanguageId($chatSession, $draft) ?? 0)?->name,
        ] : null]);
    }

    /**
     * HELP-4885: a draft the customer has written again since does not answer them, so staff do not see it.
     */
    public static function writtenBeforeCustomersLatestMessage(ChatSession|MetaChatSession $chatSession, ?ChatAiDraft $draft): bool
    {
        return $draft && $chatSession->last_visitor_message_at && $draft->created_at->lt($chatSession->last_visitor_message_at);
    }

    public const USUAL_MOOD_MIN_DRAFTS = 3;

    /**
     * The most common mood on this customer's earlier drafts, only once there are enough of them.
     * Null when the customer is not linked, the history is short or nothing leads.
     *
     * @return array{value: string, label: string, same: bool}|null
     */
    public static function usualMood(ChatSession|MetaChatSession $chatSession, ChatAiDraft $draft): ?array
    {
        $customerId = $chatSession instanceof ChatSession ? $chatSession->webUser?->customer_id : null;
        if (!$customerId) {
            return null;
        }

        $counts = ChatAiDraft::query()
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_ai_drafts.chat_session_id')
            ->join('web_users', 'web_users.id', '=', 'chat_sessions.web_user_id')
            ->where('web_users.customer_id', $customerId)
            ->where('chat_ai_drafts.id', '<', $draft->id)
            ->whereNotNull('chat_ai_drafts.mood')
            ->groupBy('chat_ai_drafts.mood')
            ->selectRaw('chat_ai_drafts.mood, count(*) as total')
            ->pluck('total', 'mood');

        if ($counts->sum() < self::USUAL_MOOD_MIN_DRAFTS) {
            return null;
        }

        $top = $counts->max();
        $leaders = $counts->filter(fn ($total) => $total === $top);
        if ($leaders->count() > 1) {
            return null;
        }

        $mood = ChatAiMoodEnum::from($leaders->keys()->first());

        return ['value' => $mood->value, 'label' => $mood->label(), 'same' => $mood === $draft->mood];
    }

    /**
     * The language an agent reads chats in: the one set in their chat settings, else the app's.
     */
    public static function staffLanguageId(?ChatAgent $agent, ?User $user): ?int
    {
        return $agent?->language_id ?? $user?->language_id;
    }

    public static function editsInOwnLanguage(?ChatAgent $agent, ?int $customerLanguageId): bool
    {
        return $customerLanguageId !== null && in_array($customerLanguageId, $agent?->edit_drafts_in_my_language_for ?? [], true);
    }

    /**
     * HELP-4534: an agent who does not write the customer's language edits the draft translated
     * into their own, and their reply is translated back before it is emailed. Remembered per
     * agent and per customer language, because many staff write some languages themselves.
     */
    public function editInMyLanguage(ChatAiDraft $chatAiDraft, Request $request): JsonResponse
    {
        $session = $chatAiDraft->session();
        $agent   = $session ? $this->getAuthorisedChatAgent($session) : null;

        if (!$agent) {
            return response()->json(['success' => false], 403);
        }

        $validated          = $request->validate(['on' => ['required', 'boolean']]);
        $customerLanguageId = self::replyLanguageId($session, $chatAiDraft);

        if (!$customerLanguageId) {
            return response()->json(['success' => false, 'message' => __('The language of this customer is not known yet')], 422);
        }

        $languages = collect($agent->edit_drafts_in_my_language_for ?? [])->reject(fn ($id) => $id === $customerLanguageId);

        $agent->update(['edit_drafts_in_my_language_for' => ($validated['on'] ? $languages->push($customerLanguageId) : $languages)->values()->all() ?: null]);

        return response()->json(['success' => true, 'data' => ['edit_in_my_language' => (bool) $validated['on']]]);
    }

    /**
     * The draft is written in the customer's language. An agent who reads another one sees it
     * translated from the start (INI-147); the language is the one the customer's message came in,
     * else the one the chat is known by, so no detection runs for it.
     */
    public static function isInAnotherLanguage(ChatSession|MetaChatSession $chatSession, ChatAiDraft $draft, ?int $agentLanguageId): bool
    {
        $replyLanguageId = self::replyLanguageId($chatSession, $draft);

        return $agentLanguageId !== null && $replyLanguageId !== null && $replyLanguageId !== $agentLanguageId;
    }

    /**
     * Aura writes her notes, questions and buttons in the organisation's language, so an agent who
     * reads another one gets them translated even when the customer writes the agent's language.
     */
    public static function notesInAnotherLanguage(ChatSession|MetaChatSession $chatSession, ChatAiDraft $draft, ?int $agentLanguageId): bool
    {
        $notesLanguageId = $chatSession->shop?->organisation?->language_id;
        $hasNotes        = data_get($draft->facts, 'staff_note') || data_get($draft->facts, 'checked') || data_get($draft->facts, 'questions') || data_get($draft->facts, 'actions');

        return $hasNotes && $agentLanguageId !== null && $notesLanguageId !== null && $notesLanguageId !== $agentLanguageId;
    }

    /**
     * Besides the language they read chats in, the other languages staff read are left as
     * written.
     */
    public static function reads(?User $user, ?int $languageId): bool
    {
        $codes = GetChatLanguagesRead::run($user);

        return $languageId !== null && $codes !== [] && in_array(Language::find($languageId)?->code, $codes, true);
    }

    public static function replyLanguageId(ChatSession|MetaChatSession $chatSession, ChatAiDraft $draft): ?int
    {
        $trigger = $draft->trigger_message_id
            ? ($chatSession instanceof ChatSession ? ChatMessage::class : MetaChatMessage::class)::find($draft->trigger_message_id)
            : null;

        return $trigger?->original_language_id ?? $chatSession->active_user_language_id ?? $chatSession->user_language_id;
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
        $agent   = $session ? $this->getAuthorisedChatAgent($session) : null;

        if (!$agent) {
            return response()->json(['success' => false], 403);
        }

        $to      = Language::find(self::staffLanguageId($agent, $request->user())) ?? Language::where('code', 'en')->firstOrFail();
        $note     = (string) data_get($chatAiDraft->facts, 'staff_note', '');
        $checked  = data_get($chatAiDraft->facts, 'checked', []);
        $labels   = collect(data_get($chatAiDraft->facts, 'actions', []))->filter(fn (array $action) => isset($action['label']))->map(fn (array $action) => $action['label']);
        $questions = array_column(data_get($chatAiDraft->facts, 'questions', []), 'text');
        $from     = $session->shop?->organisation?->language ?? DetectLanguageWithJev::run(trim($note.' '.implode(' ', $checked).' '.$labels->implode(' ').' '.implode(' ', $questions))) ?? Language::where('code', 'en')->firstOrFail();
        $translate = fn (string $text) => Translate::run($text, $from, $to);
        $replyLanguageId = self::replyLanguageId($session, $chatAiDraft);
        $replyFrom = trim((string) $chatAiDraft->text) !== ''
            ? (($replyLanguageId ? Language::find($replyLanguageId) : null) ?? DetectLanguageWithJev::run($chatAiDraft->text) ?? $from)
            : null;

        $text = $replyFrom && $replyFrom->id !== $to->id ? Translate::run($chatAiDraft->text, $replyFrom, $to) : null;

        if ($text !== null && self::isEditableTranslation($chatAiDraft->text, $text)) {
            DB::transaction(function () use ($chatAiDraft, $to, $text) {
                $locked = ChatAiDraft::lockForUpdate()->find($chatAiDraft->id);
                $locked?->update(['staff_translations' => array_replace($locked->staff_translations ?? [], [$to->id => $text])]);
            });
            $chatAiDraft->refresh();
        }

        return response()->json(['success' => true, 'data' => [
            'language'    => $to->name,
            'language_id' => $to->id,
            'text'        => $text,
            'editable'    => $text !== null && self::isEditableTranslation($chatAiDraft->text, $text),
            'staff_note' => $note !== '' ? $translate($note) : null,
            'checked'    => array_map($translate, $checked),
            'labels'     => $labels->map($translate)->all(),
            'questions'  => array_map($translate, $questions),
        ]]);
    }

    /**
     * A translation the agent may write their reply on: really translated (Translate gives the
     * text back unchanged when it fails) and keeping every [[ ]] gap, so a gap can never slip
     * past the send check by being lost in translation.
     */
    public static function isEditableTranslation(string $draft, string $translation): bool
    {
        return trim($translation) !== '' && trim($translation) !== trim($draft)
            && preg_match_all(DraftChatReply::GAP, $draft) === preg_match_all(DraftChatReply::GAP, $translation);
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
