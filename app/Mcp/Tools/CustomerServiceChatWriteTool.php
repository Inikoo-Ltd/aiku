<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Chat\ChatSession\CloseChatAfterThanks;
use App\Actions\Chat\ChatSession\DraftChatReply;
use App\Actions\Chat\ChatSession\StoreChatAiOutcome;
use App\Enums\CRM\Livechat\ChatAiOutcomeEnum;
use App\Enums\CRM\Livechat\ChatAiRunKindEnum;
use App\Models\Chat\ChatAiRun;
use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\MarkChatSessionAsSpam;
use App\Actions\Chat\ChatSession\PlanChatClaim;
use App\Actions\Chat\ChatSession\RememberVerifiedChatFact;
use App\Actions\Chat\ChatSession\RunChatAiDraftAction;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Helpers\Ticket;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use App\Actions\Chat\ChatSession\ConfirmSuggestedChatCustomer;
use App\Actions\Chat\ChatSession\LinkChatSessionCustomer;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatEvent;
use App\Actions\Chat\ChatSession\StoreAssistantChatDraft;
use App\Actions\Chat\ChatSession\SuggestChatSessionCustomer;
use App\Actions\Chat\ChatSession\WaitForCustomerReply;
use App\Actions\Helpers\Translations\DetectLanguageWithJev;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Listeners\RememberMcpClientName;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Acts on a customer chat, by its Chat ID, as the user: save_draft (puts message on the chat as a draft reply the user reviews and sends from Aiku, with an optional staff_note, the facts checked, PRE tickets and one-click actions (claim or staff task) shown on the draft; nothing reaches the customer, no confirm needed; the usual way to help with a reply; a draft with only a spam or close button and no message asks staff "send to bin?" when a chat is probably spam, or "close as done?" when an email probably only thanks us, and you are not sure), reply (sends message to the customer in the user\'s name; on an email chat it is emailed), assign_to_me, close, wait_for_reply (hours 1-720, the chat closes itself if the customer stays silent), stop_waiting, link_customer (customer = their reference, slug or email), unlink_customer, mark_spam (puts an obvious mailshot, sales pitch or platform notice in spam without blocking the sender; refused on a chat from a customer, held by somebody or already answered; revertible from ai-changes-tool), close_thanks (closes an email chat as done when the customer\'s latest message only thanks or acknowledges our reply and asks nothing; refused when somebody holds the chat, the message has a question or an order reference, or does not follow a reply from us; nothing is sent; revertible from ai-changes-tool), add_note (message = an internal note for staff, shown in the thread in pink and never sent to the customer: what was found out and what to tell them, in the user\'s language), record_outcome (outcome = why a chat was left alone: answered_by_staff, suggestion_already_waiting, held, opened, whatsapp_window_closed, answered_elsewhere, spam, thanks or notice, with a short reason; no confirm needed), start_run (run_kind, teams, model; returns a run_id), claim_chats (run_id, chat_ids, website, email and WhatsApp alike: claims the listed chats for this run before working them, so a run that starts while this one is still going leaves them alone; returns the claimed ones, the ones another run holds and the ones of shops that do not take suggestions (suggestions_off), which you must not work), finish_run (run_id, releases the run\'s claims), remember_fact (fact_title, fact, evidence: keeps a shop fact an independent reviewer checked against the tables or the code, so later runs cite it from house_rules.verified_facts of customer-service-chat-tool instead of working it out again; same title replaces it; trusted only once checked while working two different chats, then for 30 days or until staff discard, or rate as a wrong fact, a draft that cited it, or remove it; a fact that repeats a customer message, or that staff removed, is refused; never store anything a customer wrote as a fact) or dispute_fact (fact_ids, reason: a stored fact found wrong). Every chat a sweep looks at ends with exactly one of save_draft, add_note, mark_spam, close_thanks or record_outcome carrying the run_id, so the coverage report on /chat/ai counts it. Call customer-service-chat-tool first. Without confirm=true nothing happens and a preview comes back: show the user the exact reply text, in the customer\'s language, and send only after they say yes in their own words. A chat held by a colleague is refused, except save_draft once the customer has waited '.ChatSession::AI_HELD_UNANSWERED_MINUTES.' minutes there with no staff reply (inbox tool held_unanswered_only). On a WhatsApp chat the assistant only suggests: save_draft (no buttons), record_outcome, remember_fact and dispute_fact work, every other action is refused; WhatsApp takes free text only within 24 hours of the customer\'s last message, so save_draft is refused once that window has closed or fewer than '.MetaChatSession::AI_WHATSAPP_MIN_WINDOW_MINUTES.' minutes of it are left: record the outcome whatsapp_window_closed then. Only for users enrolled to serve customers through their assistant, except add_note: a note is signed by Aiku and never reaches the customer.')]
class CustomerServiceChatWriteTool extends AikuCustomerServiceTool
{
    use WithMcpChangeLog;

    public const array QUESTION_STATUSES = ['answered', 'gap'];

    private const array ACTIONS = ['save_draft', 'reply', 'assign_to_me', 'close', 'wait_for_reply', 'stop_waiting', 'link_customer', 'unlink_customer', 'mark_spam', 'close_thanks', 'add_note', 'record_outcome', 'start_run', 'claim_chats', 'finish_run', 'remember_fact', 'dispute_fact'];

    private const array BOOKKEEPING = ['add_note', 'record_outcome', 'start_run', 'claim_chats', 'finish_run'];

    private const array RUN_ACTIONS = ['start_run', 'claim_chats', 'finish_run'];

    private const array WHATSAPP_ACTIONS = ['save_draft', 'record_outcome', 'remember_fact', 'dispute_fact'];

    public function handle(Request $request): Response
    {
        $request->validate([
            'chat_id'      => ['required_unless:action,'.implode(',', self::RUN_ACTIONS), 'nullable', 'string', 'max:40'],
            'action'       => ['required', 'in:'.implode(',', self::ACTIONS)],
            'message'      => ['required_if:action,reply,add_note', 'nullable', 'string', 'max:5000'],
            'staff_note'   => ['sometimes', 'nullable', 'string', 'max:2000'],
            'checked'      => ['sometimes', 'nullable', 'array', 'max:12'],
            'checked.*'    => ['string', 'max:300'],
            'questions'          => ['sometimes', 'nullable', 'array', 'max:20'],
            'questions.*.text'   => ['required', 'string', 'max:300'],
            'questions.*.status' => ['required', 'in:'.implode(',', self::QUESTION_STATUSES)],
            'premonitions'   => ['sometimes', 'nullable', 'array', 'max:3'],
            'premonitions.*' => ['string', 'max:20'],
            'actions'                    => ['sometimes', 'nullable', 'array', 'max:4'],
            'actions.*.type'             => ['required', 'in:claim,task,spam,close'],
            'actions.*.order'            => ['required_if:actions.*.type,claim', 'nullable', 'string', 'max:64'],
            'actions.*.resolution'       => ['required_if:actions.*.type,claim', 'nullable', 'in:'.implode(',', PlanChatClaim::RESOLUTIONS)],
            'actions.*.items'            => ['required_if:actions.*.type,claim', 'nullable', 'array', 'max:80'],
            'actions.*.items.*.code'     => ['required', 'string'],
            'actions.*.items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'actions.*.reason'           => ['sometimes', 'nullable', Rule::enum(DeliveryNoteItemReplacementReasonEnum::class)],
            'actions.*.percentage'       => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:100'],
            'actions.*.label'            => ['required_if:actions.*.type,task', 'nullable', 'string', 'max:80'],
            'actions.*.subject'          => ['required_if:actions.*.type,task', 'nullable', 'string', 'max:255'],
            'actions.*.description'      => ['sometimes', 'nullable', 'string', 'max:4000'],
            'actions.*.department'       => ['sometimes', 'nullable', 'string', 'max:64'],
            'actions.*.assignee'         => ['sometimes', 'nullable', 'string', 'max:64'],
            'hours'        => ['required_if:action,wait_for_reply', 'nullable', 'integer', 'min:1', 'max:720'],
            'customer'     => ['required_if:action,link_customer', 'nullable', 'string', 'max:255'],
            'confirm'      => ['sometimes', 'boolean'],
            'request_text' => ['required_if:confirm,true', 'nullable', 'string', 'max:4000'],
            'outcome'      => ['required_if:action,record_outcome', 'nullable', 'in:'.implode(',', [...ChatAiOutcomeEnum::skips(), ChatAiOutcomeEnum::NOTE->value, ChatAiOutcomeEnum::REVIEW_FAILED_NOTE->value])],
            'reason'       => ['sometimes', 'nullable', 'string', 'max:500'],
            'run_id'       => ['required_if:action,finish_run,claim_chats', 'nullable', 'integer'],
            'chat_ids'     => ['required_if:action,claim_chats', 'nullable', 'array', 'max:300'],
            'chat_ids.*'   => ['string', 'max:40'],
            'run_kind'     => ['required_if:action,start_run', 'nullable', Rule::enum(ChatAiRunKindEnum::class)],
            'teams'        => ['sometimes', 'nullable', 'array', 'max:10'],
            'teams.*'      => ['string', 'max:40'],
            'model'        => ['sometimes', 'nullable', 'string', 'max:80'],
            'facts_used'   => ['sometimes', 'nullable', 'array', 'max:30'],
            'facts_used.*' => ['integer'],
            'fact_title'   => ['required_if:action,remember_fact', 'nullable', 'string', 'max:200'],
            'fact'         => ['required_if:action,remember_fact', 'nullable', 'string', 'max:1000'],
            'evidence'     => ['required_if:action,remember_fact', 'nullable', 'string', 'max:2000'],
            'fact_ids'     => ['required_if:action,dispute_fact', 'nullable', 'array', 'max:30'],
            'fact_ids.*'   => ['integer'],
        ]);

        $action = $request->string('action')->toString();

        $denied = in_array($action, self::BOOKKEEPING) ? null : $this->notEnrolled($request);
        if ($denied) {
            return $denied;
        }

        $run = null;
        if ($request->filled('run_id')) {
            $run = ChatAiRun::where('group_id', $request->user()->group_id)->find($request->integer('run_id'));
            if (!$run) {
                return Response::error('No run with that run_id. Start one with start_run. Nothing was changed.');
            }
        }

        if ($action === 'start_run') {
            return $this->startRun($request);
        }

        if ($action === 'claim_chats') {
            return $this->claimChats($request, $run);
        }

        if ($action === 'finish_run') {
            return $this->finishRun($run);
        }

        [$chatSession, $error] = $this->chatSessionFor($request, toActOnIt: true);
        if ($error) {
            return $error;
        }

        if ($chatSession instanceof MetaChatSession && !in_array($action, self::WHATSAPP_ACTIONS)) {
            return $this->notOnWhatsapp("Action {$action}");
        }

        $customer = null;

        if ($action === 'link_customer') {
            $customer = $this->customerToLink($chatSession, $request->string('customer')->toString());

            if (!$customer) {
                return Response::error("No customer with a login in {$chatSession->shop->name} matches '{$request->string('customer')}'. Use their reference, slug or email. Nothing was changed.");
            }
        }

        if (in_array($action, ['save_draft', 'close', 'wait_for_reply', 'stop_waiting']) && $chatSession->status === ChatSessionStatusEnum::CLOSED) {
            return Response::error('This chat is closed. Nothing was changed.');
        }

        if ($action === 'save_draft') {
            return $this->saveDraft($request, $chatSession, $run);
        }

        if ($action === 'add_note') {
            return $this->addNote($request, $chatSession, $run);
        }

        if ($action === 'record_outcome') {
            return $this->recordOutcome($request, $chatSession, $run);
        }

        if ($action === 'remember_fact') {
            return $this->rememberFact($request, $chatSession, $run);
        }

        if ($action === 'dispute_fact') {
            return $this->disputeFact($request, $chatSession);
        }

        if ($action === 'mark_spam' && $refused = MarkChatSessionAsSpam::refusedForAssistant($chatSession)) {
            return Response::error($refused.' Nothing was changed.');
        }

        $thanks = null;
        if ($action === 'close_thanks') {
            [$thanks, $refused] = CloseChatAfterThanks::thanksToClose($chatSession);
            if ($refused) {
                return Response::error($refused.' Nothing was changed.');
            }
        }

        if (!$request->boolean('confirm')) {
            return $this->askToConfirm(array_filter([
                'action'   => $action,
                'from'     => $action === 'mark_spam' ? data_get($chatSession->metadata, 'email') : null,
                'subject'  => $action === 'mark_spam' ? data_get($chatSession->metadata, 'email_subject') : null,
                'thanks'   => $thanks?->message_text,
                'chat'     => $chatSession->ulid,
                'to'       => $action === 'reply' ? (data_get($chatSession->metadata, 'email_from') ?? $this->customerOf($chatSession)?->name ?? 'the visitor') : null,
                'sent_by'  => $action === 'reply' ? ($chatSession->channel === ChatChannelEnum::EMAIL ? 'email from the shop mailbox' : 'chat') : null,
                'message'  => $action === 'reply' ? $request->get('message') : null,
                'hours'    => $action === 'wait_for_reply' ? $request->integer('hours') : null,
                'customer' => $customer ? $customer->name.' ('.$customer->reference.', '.$customer->email.')' : null,
            ]));
        }

        try {
            $this->recordChange(
                $request,
                match ($action) {
                    'mark_spam'    => McpChangeTypeEnum::CHAT_SPAM,
                    'close_thanks' => McpChangeTypeEnum::CHAT_CLOSED,
                    default        => McpChangeTypeEnum::CUSTOMER_SERVICE,
                },
                str_replace('_', ' ', $action).' on chat '.$chatSession->ulid,
                ['chat_session_id' => $chatSession->id],
                fn () => $this->act($chatSession, $action, $request, $customer),
                ['action' => $action, 'message' => $request->get('message')]
            );

            if (in_array($action, ['mark_spam', 'close_thanks'])) {
                StoreChatAiOutcome::run($chatSession, $action === 'mark_spam' ? ChatAiOutcomeEnum::SPAM : ChatAiOutcomeEnum::THANKS, [
                    'reason' => $request->get('reason'),
                    'run_id' => $run?->id,
                ]);
            }
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was changed.');
        }

        $chatSession->refresh();

        return Response::json([
            'done'          => $action,
            'chat'          => $chatSession->ulid,
            'status'        => $chatSession->status?->value,
            'waiting_until' => WaitForCustomerReply::until($chatSession),
            'customer'      => $this->customerOf($chatSession)?->name,
            'change_log_id' => $this->mcpChange?->id,
        ]);
    }

    /**
     * A draft only lands in the agent's draft box, so it needs no confirmation: discarding it there
     * is the undo. It is refused on a colleague's chat, in a language the customer does not write,
     * and on WhatsApp once too little of the 24-hour window is left for staff to send it.
     */
    private function saveDraft(Request $request, ChatSession|MetaChatSession $chatSession, ?ChatAiRun $run): Response
    {
        if (!$this->userCanDisposeOfChat($request->user(), $chatSession) && !$chatSession->newQuery()->whereKey($chatSession->id)->heldUnansweredForAiku()->exists()) {
            return Response::error($this->chatHeldByAnotherAgentMessage($chatSession).' A draft is allowed on a held chat only once the customer has waited '.ChatSession::AI_HELD_UNANSWERED_MINUTES.' minutes with no staff reply. Nothing was saved.');
        }

        if (!$chatSession->shop->send_chat_ai_suggestions) {
            return Response::error("{$chatSession->shop->name} does not take suggestions. Nothing was saved.");
        }

        if ($chatSession instanceof MetaChatSession) {
            if ($closed = $this->whatsappWindowClosed($chatSession)) {
                return Response::error($closed.' Call record_outcome with outcome whatsapp_window_closed instead. Nothing was saved.');
            }

            if ($request->get('actions')) {
                return Response::error('One-click buttons (claim, task, spam, close) are not available on WhatsApp chats: say what staff must do in staff_note. Nothing was saved.');
            }
        }

        $text      = trim($request->string('message')->toString());
        $onlyAsksStaff = (bool) array_intersect(['spam', 'close'], array_column($request->get('actions') ?? [], 'type'));
        if ($text === '' && !$onlyAsksStaff) {
            return Response::error('A draft needs a message, unless it only asks staff whether the chat is spam or can be closed. Nothing was saved.');
        }

        $language = $this->replyLanguage($chatSession);
        $written  = $language && $text !== '' ? DetectLanguageWithJev::run($text, [$language]) : null;

        if ($written && $written->id !== $language->id) {
            return Response::error("Write the draft in {$language->name}, the language of this conversation. Nothing was saved.");
        }

        $actions = [];
        foreach (array_values($request->get('actions') ?? []) as $position => $action) {
            $refused = $this->refusedDraftAction($chatSession, $action);
            if ($refused) {
                return Response::error('Action '.($position + 1).": $refused Nothing was saved.");
            }
            $actions[] = array_filter(Arr::only($action, ['type', 'order', 'resolution', 'items', 'reason', 'percentage', 'label', 'subject', 'description', 'department', 'assignee']), fn ($value) => $value !== null && $value !== '');
        }

        $premonitions = Ticket::where('group_id', $chatSession->shop->group_id)
            ->where('type', TicketTypeEnum::PREMONITION)
            ->whereIn('reference', array_map('strtoupper', $request->get('premonitions') ?? []))
            ->pluck('reference')
            ->all();

        $draft = StoreAssistantChatDraft::run(
            $chatSession,
            $request->user(),
            $text,
            $request->sessionId() ? Cache::get(RememberMcpClientName::key($request->sessionId())) : null
        );

        $questions = array_values(array_filter(array_map(fn (array $question) => [
            'text'   => trim($question['text']),
            'status' => $question['status'],
        ], $request->get('questions') ?? []), fn (array $question) => $question['text'] !== ''));

        $draft->update(['facts' => $draft->facts + array_filter([
            'staff_note'       => trim((string) $request->get('staff_note')) ?: null,
            'checked'          => array_values(array_filter(array_map('trim', $request->get('checked') ?? []))),
            'premonitions'     => $premonitions,
            'actions'          => $actions,
            'questions'        => $questions,
            'since_last_reply' => $questions ? $this->sinceLastReply($chatSession) : null,
            'facts_used'       => $request->get('facts_used') ? RememberVerifiedChatFact::trusted($chatSession->shop)->whereIn('id', $request->get('facts_used'))->pluck('id')->all() : null,
        ])]);

        StoreChatAiOutcome::run($chatSession, ChatAiOutcomeEnum::DRAFT, [
            'chat_ai_draft_id'   => $draft->id,
            'trigger_message_id' => $draft->trigger_message_id,
            'run_id'             => $run?->id,
        ]);

        return Response::json([
            'saved'     => 'draft',
            'chat'      => $chatSession->ulid,
            'draft_id'  => $draft->id,
            'next_step' => 'Nothing was sent. The draft is waiting on the chat in Aiku, where the user can send it, edit it or discard it. Any [[gap]] left in it must be filled there before it can be sent.',
        ]);
    }

    /**
     * What the customer wrote after our last reply, as it stood when the draft answering it was saved.
     *
     * @return array{count: int, first_at: string, last_at: string, after_reply: bool}|null
     */
    private function sinceLastReply(ChatSession|MetaChatSession $chatSession): ?array
    {
        $lastReplyId = $chatSession->messages()->whereIn('sender_type', [ChatSenderTypeEnum::AGENT, ChatSenderTypeEnum::AI])->max('id');

        $written = $chatSession->messages()
            ->whereIn('sender_type', [ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::USER])
            ->when($lastReplyId, fn ($query) => $query->where('id', '>', $lastReplyId))
            ->selectRaw('count(*) as count, min(created_at) as first_at, max(created_at) as last_at')
            ->toBase()
            ->first();

        if (!$written?->count) {
            return null;
        }

        return [
            'count'       => (int) $written->count,
            'first_at'    => Carbon::parse($written->first_at)->toIso8601String(),
            'last_at'     => Carbon::parse($written->last_at)->toIso8601String(),
            'after_reply' => (bool) $lastReplyId,
        ];
    }

    /**
     * A claim button is planned when the draft is saved, so a draft never offers a refund or a
     * replacement that the order no longer allows; a task needs someone to do it.
     *
     * @param  array<string, mixed>  $action
     */
    private function refusedDraftAction(ChatSession $chatSession, array $action): ?string
    {
        if ($action['type'] === 'spam') {
            return MarkChatSessionAsSpam::refusedForAssistant($chatSession);
        }

        if ($action['type'] === 'close') {
            return CloseChatAfterThanks::thanksToClose($chatSession)[1];
        }

        if ($action['type'] === 'task') {
            return empty($action['department']) && empty($action['assignee']) ? 'a task needs a department or an assignee.' : null;
        }

        $order = RunChatAiDraftAction::order($chatSession, (string) $action['order']);

        if (!$order) {
            return "{$action['order']} is not an order of this customer.";
        }

        return PlanChatClaim::run($chatSession, $order, $action['resolution'], $action['items'], $action['reason'] ?? null, isset($action['percentage']) ? (float) $action['percentage'] : null)['error'];
    }

    /**
     * A note stays in the thread for every colleague to read and has no undo, so it is previewed first.
     * It is allowed on a colleague's chat and on a closed one: that is where what was found out is needed.
     */
    private function addNote(Request $request, ChatSession $chatSession, ?ChatAiRun $run): Response
    {
        $text    = trim($request->string('message')->toString());
        $outcome = $request->get('outcome') === ChatAiOutcomeEnum::REVIEW_FAILED_NOTE->value ? ChatAiOutcomeEnum::REVIEW_FAILED_NOTE : ChatAiOutcomeEnum::NOTE;

        if (!$request->boolean('confirm')) {
            return $this->askToConfirm(['action' => 'add_note', 'chat' => $chatSession->ulid, 'note' => $text]);
        }

        $note = StoreChatEvent::make()->internalNote($chatSession, $text, $request->user()->id);

        StoreChatAiOutcome::run($chatSession, $outcome, [
            'note_event_id' => $note->id,
            'reason'        => $request->get('reason'),
            'run_id'        => $run?->id,
        ]);

        return Response::json([
            'done'    => 'add_note',
            'chat'    => $chatSession->ulid,
            'note_id' => $note->id,
        ]);
    }

    /**
     * Why the sweep left a chat alone, so the coverage report counts it instead of showing it as not
     * worked. Nothing reaches anybody, so no preview; the reasons the tables can check are checked.
     */
    private function recordOutcome(Request $request, ChatSession|MetaChatSession $chatSession, ?ChatAiRun $run): Response
    {
        $outcome = ChatAiOutcomeEnum::from($request->string('outcome')->toString());

        if (!in_array($outcome->value, ChatAiOutcomeEnum::skips())) {
            return Response::error('record_outcome is for chats left alone; a note is recorded by add_note. Nothing was recorded.');
        }

        $triggerId = StoreChatAiOutcome::latestCustomerMessageId($chatSession);
        if (!$triggerId) {
            return Response::error('The customer has not written in this chat, so there is nothing to account for. Nothing was recorded.');
        }

        $refused = match ($outcome) {
            ChatAiOutcomeEnum::ANSWERED_BY_STAFF => $chatSession->messages()->where('sender_type', ChatSenderTypeEnum::AGENT)->where('id', '>', $triggerId)->exists()
                ? null : 'No staff reply after the customer\'s latest message.',
            ChatAiOutcomeEnum::SUGGESTION_ALREADY_WAITING => DraftChatReply::pendingDraft($chatSession)
                ? null : 'No suggestion is waiting on this chat.',
            ChatAiOutcomeEnum::HELD => !$this->userCanDisposeOfChat($request->user(), $chatSession)
                ? null : 'Nobody else holds this chat.',
            ChatAiOutcomeEnum::OPENED => $chatSession->newQuery()->whereKey($chatSession->id)->openedUnansweredForAiku()->exists()
                ? null : 'This is not a chat a colleague opened and left unanswered for '.ChatSession::AI_OPENED_UNANSWERED_MINUTES.' minutes (inbox tool opened_unanswered_only).',
            ChatAiOutcomeEnum::WHATSAPP_WINDOW_CLOSED => match (true) {
                !$chatSession instanceof MetaChatSession   => 'whatsapp_window_closed is only for WhatsApp chats.',
                !$this->whatsappWindowClosed($chatSession) => 'The WhatsApp window is still open for '.$this->whatsappWindow($chatSession)['whatsapp_window_minutes_left'].' minutes: save a draft instead.',
                default                                    => null,
            },
            default => null,
        };

        if ($refused) {
            return Response::error($refused.' Nothing was recorded.');
        }

        $recorded = StoreChatAiOutcome::run($chatSession, $outcome, [
            'reason'             => $request->get('reason'),
            'run_id'             => $run?->id,
            'trigger_message_id' => $triggerId,
        ]);

        return Response::json([
            'done'       => 'record_outcome',
            'chat'       => $chatSession->ulid,
            'outcome'    => $recorded->outcome->value,
            'outcome_id' => $recorded->id,
        ]);
    }

    /**
     * A reviewer keeps a shop fact it checked, with the SQL, table row or file and method that
     * proves it, so later runs cite it. Nothing reaches a customer, so no preview.
     */
    private function rememberFact(Request $request, ChatSession|MetaChatSession $chatSession, ?ChatAiRun $run): Response
    {
        $title = trim($request->string('fact_title')->toString());
        $text  = trim($request->string('fact')->toString());

        if ($refused = RememberVerifiedChatFact::refusal($chatSession, $title, $text)) {
            return Response::error($refused.' Nothing was stored.');
        }

        $fact = RememberVerifiedChatFact::run($chatSession, $request->user(), $title, $text, trim($request->string('evidence')->toString()), $run?->id);

        return Response::json([
            'done'          => 'remember_fact',
            'fact_id'       => $fact->id,
            'shop'          => $chatSession->shop->slug,
            'status'        => $fact->status,
            'trusted_until' => $fact->status === 'active' ? $fact->expires_at?->toIso8601String() : null,
            'next_step'     => $fact->status === 'active'
                ? 'Pass fact_id in facts_used on every save_draft that relies on it.'
                : 'Stored as a candidate: it is trusted once checked again while working a different chat. Until then check it each time and do not pass it in facts_used.',
        ]);
    }

    private function disputeFact(Request $request, ChatSession|MetaChatSession $chatSession): Response
    {
        $disputed = RememberVerifiedChatFact::dispute(
            $chatSession->shop,
            array_map('intval', $request->get('fact_ids')),
            trim((string) $request->get('reason')) ?: 'Found wrong by a reviewer',
            userId: $request->user()->id
        );

        return Response::json([
            'done'     => 'dispute_fact',
            'disputed' => $disputed,
        ]);
    }

    private function startRun(Request $request): Response
    {
        $run = ChatAiRun::create([
            'group_id'           => $request->user()->group_id,
            'kind'               => ChatAiRunKindEnum::from($request->string('run_kind')->toString()),
            'teams'              => array_values($request->get('teams') ?? []),
            'model'              => $request->get('model'),
            'started_by_user_id' => $request->user()->id,
            'started_at'         => now(),
        ]);

        return Response::json([
            'done'      => 'start_run',
            'run_id'    => $run->id,
            'next_step' => 'Pass this run_id to every save_draft, add_note and record_outcome of this run, then call finish_run.',
        ]);
    }

    /**
     * One UPDATE per chat table takes every listed chat that no other run is still working, so two
     * runs that listed the same chats at the same moment can never both end up holding one.
     */
    private function claimChats(Request $request, ChatAiRun $run): Response
    {
        if ($run->finished_at) {
            return Response::error('This run has finished. Start a new one with start_run. Nothing was changed.');
        }

        $chatIds        = array_values(array_unique($request->get('chat_ids')));
        $claimed        = 0;
        $mine           = [];
        $suggestionsOff = [];

        foreach ([ChatSession::class, MetaChatSession::class] as $sessionClass) {
            $claimed += $sessionClass::whereIn('ulid', $chatIds)
                ->whereHas('shop', fn ($shop) => $shop->where('group_id', $run->group_id)->where('send_chat_ai_suggestions', true))
                ->notClaimedByAnotherAiRun($run->id)
                ->update(['ai_claim_run_id' => $run->id, 'ai_claimed_at' => now()]);

            $mine = [...$mine, ...$sessionClass::whereIn('ulid', $chatIds)->where('ai_claim_run_id', $run->id)->pluck('ulid')->all()];

            $suggestionsOff = [...$suggestionsOff, ...$sessionClass::whereIn('ulid', $chatIds)
                ->whereHas('shop', fn ($shop) => $shop->where('group_id', $run->group_id)->where('send_chat_ai_suggestions', false))
                ->pluck('ulid')->all()];
        }

        return Response::json([
            'done'               => 'claim_chats',
            'run_id'             => $run->id,
            'claimed'            => $claimed,
            'work_these'         => $mine,
            'held_by_other_runs' => array_values(array_diff($chatIds, $mine, $suggestionsOff)),
            'suggestions_off'    => $suggestionsOff,
        ]);
    }

    private function finishRun(ChatAiRun $run): Response
    {
        $counts = [];
        foreach ($run->outcomes()->selectRaw('outcome, channel, count(*) as total')->groupBy('outcome', 'channel')->toBase()->get() as $row) {
            $counts[$row->outcome][$row->channel] = (int) $row->total;
        }

        $run->update(['finished_at' => now(), 'counts' => $counts]);

        foreach ([ChatSession::class, MetaChatSession::class] as $sessionClass) {
            $sessionClass::where('ai_claim_run_id', $run->id)->update(['ai_claim_run_id' => null, 'ai_claimed_at' => null]);
        }

        return Response::json([
            'done'   => 'finish_run',
            'run_id' => $run->id,
            'counts' => $counts,
        ]);
    }

    /**
     * WhatsApp takes free text only within 24 hours of the customer's last message, so a draft needs
     * enough of that window left for staff to review it and send it.
     */
    private function whatsappWindowClosed(MetaChatSession $chatSession): ?string
    {
        if (!$chatSession->can_send_non_template_message) {
            return 'The WhatsApp 24-hour window has closed: only an approved template can reach the customer now.';
        }

        $minutesLeft = $this->whatsappWindow($chatSession)['whatsapp_window_minutes_left'];

        if ($minutesLeft < MetaChatSession::AI_WHATSAPP_MIN_WINDOW_MINUTES) {
            return "Only {$minutesLeft} minutes are left of the WhatsApp 24-hour window, too few for staff to review and send a draft.";
        }

        return null;
    }

    /**
     * @throws \Throwable
     */
    private function act(ChatSession $chatSession, string $action, Request $request, ?Customer $customer): void
    {
        match ($action) {
            'reply'           => SendChatMessage::make()->replyAsAgent($chatSession, $request->string('message')->toString()),
            'assign_to_me'    => $this->claim($chatSession, $request),
            'close'           => CloseChatSession::make()->handle($chatSession, $this->chatAgent->id),
            'wait_for_reply'  => WaitForCustomerReply::make()->handle($chatSession, $request->integer('hours')),
            'stop_waiting'    => WaitForCustomerReply::stop($chatSession),
            'link_customer'   => $this->link($chatSession, $customer),
            'unlink_customer' => $this->unlink($chatSession),
            'mark_spam'       => MarkChatSessionAsSpam::make()->handle($chatSession, $this->chatAgent, blockSender: false),
            'close_thanks'    => CloseChatAfterThanks::closeForAssistant($chatSession),
        };
    }

    /**
     * Picked up the way a reply picks a chat up, not through the routing pool: whoever asks their
     * assistant is rarely sitting in the inbox, and being offline there must not stop them.
     *
     * @throws ValidationException
     */
    private function claim(ChatSession $chatSession, Request $request): void
    {
        $refusal = SendChatMessage::make()->claimUnheldChat($chatSession, $request->user(), $this->chatAgent);

        if ($refusal) {
            throw ValidationException::withMessages(['chat_id' => $refusal['message']]);
        }
    }

    private function customerToLink(ChatSession $chatSession, string $identifier): ?Customer
    {
        $identifier = strtolower(trim($identifier));

        return Customer::where('shop_id', $chatSession->shop_id)
            ->whereHas('webUsers')
            ->where(fn ($query) => $query->whereRaw('lower(reference) = ?', [$identifier])
                ->orWhereRaw('lower(slug) = ?', [$identifier])
                ->orWhereRaw('lower(email) = ?', [$identifier]))
            ->first();
    }

    private function link(ChatSession $chatSession, Customer $customer): void
    {
        $chatSession->update([
            'suggested_customer_id'  => $customer->id,
            'suggestion_basis'       => SuggestChatSessionCustomer::BASIS_MANUAL,
            'suggestion_rejected_at' => null,
        ]);

        ConfirmSuggestedChatCustomer::make()->handle($chatSession, $this->chatAgent, true);
    }

    /**
     * @throws ValidationException
     */
    private function unlink(ChatSession $chatSession): void
    {
        $response = LinkChatSessionCustomer::make()->unlink($chatSession);

        if (!data_get($response->getData(true), 'success')) {
            throw ValidationException::withMessages(['customer' => data_get($response->getData(true), 'message', 'The customer could not be unlinked.')]);
        }
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'chat_id'      => $schema->string()->description('Chat ID copied from the Overview panel of the chat; not needed for start_run, claim_chats and finish_run'),
            'action'       => $schema->string()->enum(self::ACTIONS)->description('What to do on the chat')->required(),
            'message'      => $schema->string()->description('For add_note: the note for staff. For save_draft (may be left out with a spam or close action) and reply: the full text for the customer, plain text, in the language reply_in names, signed as the user would sign it. In a draft, [[what to check]] marks something only the user can decide'),
            'staff_note'   => $schema->string()->description('For save_draft only: a short note for staff shown on the draft itself, never sent to the customer, in the user\'s language. Start it with "Before sending:" when staff must do something first (refund, credit, replacement); otherwise what was checked and anything uncertain'),
            'checked'      => $schema->array()->items($schema->string())->description('For save_draft only: up to 12 short lines of what was checked in Aiku for this answer, each a fact with its reference (e.g. "AWD196419 dispatched 13 Oct, Royal Mail XQ573801258GB"), shown to staff on the draft'),
            'questions'    => $schema->array()->items($schema->object([
                'text'   => $schema->string()->description('one short line, in the user\'s language')->required(),
                'status' => $schema->string()->enum(self::QUESTION_STATUSES)->description('answered when the draft answers it, gap when the draft leaves it to staff as a [[...]]')->required(),
            ]))->description('For save_draft only: every distinct question or request the customer sent since our last reply, up to 20, in the order they came, shown to staff as a checklist with how many times the customer wrote and their other open chats, so nothing is missed'),
            'premonitions' => $schema->array()->items($schema->string())->description('For save_draft only: PRE ticket references raised or found for a problem in this chat, shown to staff on the draft with their live status'),
            'actions'      => $schema->array()->items($schema->object([
                'type'        => $schema->string()->enum(['claim', 'task', 'spam', 'close'])->required(),
                'order'       => $schema->string()->description('claim: the dispatched order reference'),
                'resolution'  => $schema->string()->enum(PlanChatClaim::RESOLUTIONS)->description('claim: refund_to_balance, replacement or next_order'),
                'items'       => $schema->array()->items($schema->object([
                    'code'     => $schema->string()->required(),
                    'quantity' => $schema->number()->required(),
                ]))->description('claim: product codes and quantities, from the claimable list'),
                'reason'      => $schema->string()->enum(array_column(DeliveryNoteItemReplacementReasonEnum::cases(), 'value'))->description('claim: why'),
                'percentage'  => $schema->number()->description('claim refund_to_balance: part to refund, 100 by default'),
                'label'       => $schema->string()->description('task: the button text, short, in the user\'s language, e.g. "Ask the warehouse to dispatch AWD197367 today"'),
                'subject'     => $schema->string()->description('task: the task subject for the department'),
                'description' => $schema->string()->description('task: what to do and why, with references'),
                'department'  => $schema->string()->description('task: department slug, e.g. warehouse'),
                'assignee'    => $schema->string()->description('task: aiku username, instead of a department'),
            ]))->description('For save_draft only: up to 4 one-click buttons shown on the draft for what staff must do before sending: settle a claim (the amount is shown and checked when clicked) or ask a department as a staff task, or, with type spam or close and no message, ask staff whether to send a chat that is probably spam to the bin, or to close as done an email that probably only thanks us. Nothing runs until a staff member clicks. Claims are checked when the draft is saved and a draft with one that cannot be done is refused'),
            'hours'        => $schema->integer()->description('For wait_for_reply: hours to wait for the customer, 24, 72 or 168 are the usual ones')->min(1)->max(720),
            'customer'     => $schema->string()->description('For link_customer: the customer\'s reference, slug or email'),
            'confirm'      => $schema->boolean()->description('true only after the user has seen the preview and said yes'),
            'request_text' => $schema->string()->description('The user\'s request, verbatim, kept in the AI changes log; required with confirm=true'),
            'outcome'      => $schema->string()->enum([...ChatAiOutcomeEnum::skips(), ChatAiOutcomeEnum::NOTE->value, ChatAiOutcomeEnum::REVIEW_FAILED_NOTE->value])->description('For record_outcome: why the chat was left alone (answered_by_staff, suggestion_already_waiting, held, opened, whatsapp_window_closed, answered_elsewhere, spam, thanks, notice); whatsapp_window_closed only on a WhatsApp chat whose 24-hour window has closed or has fewer than '.MetaChatSession::AI_WHATSAPP_MIN_WINDOW_MINUTES.' minutes left, where only an approved template can be sent. For add_note: review_failed_note when the note replaces a draft that failed review, note otherwise'),
            'reason'       => $schema->string()->description('For record_outcome, add_note, mark_spam and close_thanks: one short line on why, for the coverage report'),
            'run_id'       => $schema->integer()->description('The run_id start_run returned; pass it to claim_chats, save_draft, add_note, record_outcome, mark_spam and close_thanks, and to finish_run'),
            'chat_ids'     => $schema->array()->items($schema->string())->description('For claim_chats: the Chat IDs this run is about to work, up to 300'),
            'run_kind'     => $schema->string()->enum(array_column(ChatAiRunKindEnum::cases(), 'value'))->description('For start_run: hourly, early_sweep or manual'),
            'teams'        => $schema->array()->items($schema->string())->description('For start_run: the teams this run covers, e.g. ["es_sk", "aw_aroma"]'),
            'model'        => $schema->string()->description('For start_run: the model running the sweep'),
            'facts_used'   => $schema->array()->items($schema->integer())->description('For save_draft only: the fact_id of every verified fact (house_rules.verified_facts or remember_fact) the draft relies on. If staff discard the draft or rate it a wrong fact, those facts stop being trusted until checked again'),
            'fact_title'   => $schema->string()->description('For remember_fact: a short stable title, e.g. "awd small order charge"; the same title replaces the older version of the fact'),
            'fact'         => $schema->string()->description('For remember_fact: the shop fact as checked, one or two sentences in English. Only facts that hold for every customer of the shop (charges and their triggers, shipping zones and weight steps, VAT, registration, integrations, policy page text); never one order, payment, parcel, balance or stock level'),
            'evidence'     => $schema->string()->description('For remember_fact: how it was checked: the SQL and the rows, or the file and method at origin/production, or the page URL'),
            'fact_ids'     => $schema->array()->items($schema->integer())->description('For dispute_fact: verified facts found wrong, with reason saying what is wrong'),
        ];
    }
}
