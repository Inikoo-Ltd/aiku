<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\Reports\IsWithinWorkingHours;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverReasonEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverRouteEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\Chat\ChatSession;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantHandOver;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Passes one question of an assistant conversation to a person, through the chat system the team
 * already works in. The customer asks for it: by tapping the button under an answer that offered a
 * person ($offerMessage), or with the Talk to a person link and a typed question ($typedMessage).
 * Nothing here calls a model, and no AI answer is queued for a typed question.
 *
 * The route comes from GetShoppingAssistantHandOverRoute: a live chat (website chat, an agent is
 * free), an email chat (the team replies from the shop mailbox to the customer's address) or a web
 * chat (the reply waits in the website chat window). With no route a person cannot be reached and
 * nothing is done.
 *
 * - The chat is a normal waiting, unassigned chat session, marked with the conversation it came
 *   from, so the unclaimed queue and its alerts pick it up. No assignment is made.
 * - When the customer already has a hand-over on this website whose chat is still waiting or active,
 *   in this conversation or an earlier one, the new question goes into that same chat, with that
 *   hand-over's route; the customer is not given a second chat.
 * - A customer can hand over at most MAX_PER_DAY questions a day on a website: each one is work for
 *   a person, and each chat message costs an AI check of its own that the assistant's budget does not cover.
 * - An email chat carries the question in its subject, because an email chat without a Gmail thread
 *   starts one on the agent's first reply and that mail does not quote the customer.
 * - Only the question is written into the chat, as the customer's own message. The transcript is NOT
 *   copied: its assistant answers would count as agent first replies and its customer lines as
 *   customer messages in the chat coverage and reply-time reports. The inbox reads the transcript
 *   from the assistant's own tables, through the chat's shopping_assistant_conversation_id.
 * - Asking twice for the same question (a double tap) returns the first hand-over and creates nothing:
 *   the unique customer_message_id backs this for the button under an answer when two requests race,
 *   and the same typed question within a minute is taken to be the same request.
 * - The customer is told what happens next in the conversation with a fixed sentence in the
 *   conversation's language. The conversation stays open so they can keep asking the assistant.
 *
 * The chat session, hand-over row, assistant sentence and conversation update are written in one
 * transaction. The question is sent into the chat after it commits: SendChatMessage dispatches
 * queued jobs and broadcasts, which must not run for a chat that could still roll back. If that send
 * fails the hand-over stands and the question is still on the hand-over row.
 *
 * The customer's message in the chat fires what any customer message fires: the session's last
 * visitor message time is set, a chat event is logged, the out-of-hours auto reply and the urgent
 * request check are queued (website chats only), the message is queued for translation and
 * broadcast to the chat window and the chat list. No email is sent for it: only an agent's message
 * on an email chat is emailed.
 */
class HandOverShoppingAssistantConversation
{
    use AsAction;

    public const int MAX_PER_DAY = 10;

    private const int SAME_QUESTION_SECONDS = 60;

    /**
     * @return array{hand_over: ShoppingAssistantHandOver, message: ShoppingAssistantMessage, chat_session: ChatSession}|null
     *
     * @throws \Throwable
     */
    public function handle(ShoppingAssistantConversation $conversation, ?ShoppingAssistantMessage $offerMessage = null, ?string $typedMessage = null): ?array
    {
        $website  = $conversation->website;
        $customer = $conversation->customer;
        if (!$website || !$customer) {
            return null;
        }

        $status = GetShoppingAssistantHandOverRoute::run($website, $customer);
        if (!$status['route']) {
            return null;
        }

        $typedMessage = filled(trim((string) $typedMessage)) ? trim((string) $typedMessage) : null;
        $offerMessage = $typedMessage === null ? $this->validatedOffer($conversation, $offerMessage) : null;

        $existing = $offerMessage
            ? $this->existingResult($conversation, $this->questionFor($conversation, $offerMessage))
            : $this->recentTypedHandOver($customer, $website, $typedMessage);
        if ($existing) {
            return $existing;
        }

        if ($this->limitReached($customer, $website)) {
            throw new TooManyRequestsHttpException();
        }

        try {
            $result = DB::transaction(fn () => $this->open($conversation, $customer, $status, $offerMessage, $typedMessage));
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $offerMessage ? $this->existingResult($conversation, $this->questionFor($conversation, $offerMessage)) : null;
            if (!$existing) {
                throw $exception;
            }

            return $existing;
        }

        $this->sendQuestion($result['chat_session'], $result['hand_over']);

        return $result;
    }

    /**
     * The same typed question from the same customer in the last minute: a double tap of the Talk to
     * a person form, which must not open a second conversation or chat.
     *
     * @return array{hand_over: ShoppingAssistantHandOver, message: ShoppingAssistantMessage, chat_session: ChatSession}|null
     */
    public function recentTypedHandOver(Customer $customer, Website $website, string $typedMessage): ?array
    {
        $handOver = $this->handOversOf($customer, $website)
            ->whereNull('offer_message_id')
            ->where('question', mb_substr(trim($typedMessage), 0, AskShoppingAssistant::MAX_MESSAGE))
            ->where('created_at', '>=', now()->subSeconds(self::SAME_QUESTION_SECONDS))
            ->orderByDesc('id')
            ->first();

        return $handOver ? $this->resultOf($handOver) : null;
    }

    public function limitReached(Customer $customer, Website $website): bool
    {
        return $this->handOversOf($customer, $website)->where('created_at', '>=', now()->subDay())->count() >= self::MAX_PER_DAY;
    }

    /**
     * @return Builder<ShoppingAssistantHandOver>
     */
    private function handOversOf(Customer $customer, Website $website): Builder
    {
        return ShoppingAssistantHandOver::query()->whereIn(
            'shopping_assistant_conversation_id',
            ShoppingAssistantConversation::query()->where('customer_id', $customer->id)->where('website_id', $website->id)->select('id')
        );
    }

    private function sendQuestion(ChatSession $session, ShoppingAssistantHandOver $handOver): void
    {
        SendChatMessage::run($session, [
            'message_text' => $handOver->question,
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'sender_type'  => $session->web_user_id ? ChatSenderTypeEnum::USER->value : ChatSenderTypeEnum::GUEST->value,
            'sender_id'    => $session->web_user_id,
        ]);
    }

    /**
     * @param  array{route: ?ShoppingAssistantHandOverRouteEnum, in_hours: bool, agent_available: bool}  $status
     *
     * @return array{hand_over: ShoppingAssistantHandOver, message: ShoppingAssistantMessage, chat_session: ChatSession}
     */
    private function open(ShoppingAssistantConversation $conversation, Customer $customer, array $status, ?ShoppingAssistantMessage $offerMessage, ?string $typedMessage): array
    {
        if ($typedMessage !== null) {
            $question = AskShoppingAssistant::make()->storeCustomerMessage($conversation, $typedMessage);
            $reason   = ShoppingAssistantHandOverReasonEnum::ASKED_FOR_PERSON;
        } else {
            $question = $this->questionFor($conversation, $offerMessage);
            $reason   = $offerMessage->hand_over_offer;
        }

        $earlier = $this->handOversOf($customer, $conversation->website)->whereNotNull('chat_session_id')->orderByDesc('id')->first();
        $session = $earlier ? ChatSession::whereKey($earlier->chat_session_id)
            ->whereIn('status', [ChatSessionStatusEnum::WAITING->value, ChatSessionStatusEnum::ACTIVE->value])
            ->first() : null;

        $route = $session ? $earlier->route : $status['route'];
        $session ??= $this->createSession($conversation, $customer, $route, $question);

        $handOver = $conversation->handOvers()->create([
            'reason'              => $reason,
            'route'               => $route,
            'in_hours'            => $status['in_hours'],
            'agent_available'     => $status['agent_available'],
            'question'            => $question->body,
            'customer_message_id' => $question->id,
            'offer_message_id'    => $offerMessage?->id,
            'chat_session_id'     => $session->id,
        ]);

        $message = $this->tellCustomer($conversation, $route, $status['in_hours']);

        $conversation->update([
            'handed_over_at'  => now(),
            'chat_session_id' => $session->id,
        ]);

        return ['hand_over' => $handOver, 'message' => $message, 'chat_session' => $session];
    }

    private function createSession(ShoppingAssistantConversation $conversation, Customer $customer, ShoppingAssistantHandOverRouteEnum $route, ShoppingAssistantMessage $question): ChatSession
    {
        $shop      = $conversation->shop;
        $webUserId = $conversation->web_user_id
            ?? $customer->webUsers()->where('email', $customer->email)->value('id')
            ?? $customer->webUsers()->value('id');
        $isEmail   = $route === ShoppingAssistantHandOverRouteEnum::EMAIL_CHAT;

        $session = StoreChatSession::run([
            'shop_id'             => $shop->id,
            'trusted_web_user_id' => $webUserId,
            'language_id'         => $shop->language_id,
            'priority'            => ChatPriorityEnum::NORMAL,
            'channel'             => $isEmail ? ChatChannelEnum::EMAIL : ChatChannelEnum::WEBSITE,
        ]);

        $update = ['shopping_assistant_conversation_id' => $conversation->id];

        if ($isEmail) {
            $name           = $customer->contact_name ?: $customer->name;
            $update['metadata'] = array_merge($session->metadata ?? [], [
                'email_subject'   => __('Your question: :question', ['question' => Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) $question->body)), 120)], $conversation->language_code),
                'email_from'      => $customer->email,
                'email_from_name' => $name,
                'name'            => $name,
                'email'           => $customer->email,
            ]);
        }

        $session->update($update);

        return $session;
    }

    private function tellCustomer(ShoppingAssistantConversation $conversation, ShoppingAssistantHandOverRouteEnum $route, bool $inHours): ShoppingAssistantMessage
    {
        $locale  = $conversation->language_code;
        $shop    = $conversation->shop;
        $opening = $inHours ? null : IsWithinWorkingHours::make()->chatHours()->nextOpening($shop, now());
        $replace = $opening ? [
            'day'  => $opening['opens']->copy()->locale($locale)->isoFormat('dddd'),
            'time' => $shop->organisation->formatClockTime($opening['opens']),
        ] : [];

        $body = match ($route) {
            ShoppingAssistantHandOverRouteEnum::LIVE_CHAT  => __('I am passing you to our team. Your chat with them is opening now.', [], $locale),
            ShoppingAssistantHandOverRouteEnum::EMAIL_CHAT => $opening
                ? __('Our team is back on :day at :time and will reply by email, to the address on your account.', $replace, $locale)
                : __('Nobody from our team is free right now. They will reply by email, to the address on your account.', [], $locale),
            ShoppingAssistantHandOverRouteEnum::WEB_CHAT   => $opening
                ? __('Our team is back on :day at :time and will reply in the chat on this website.', $replace, $locale)
                : __('Nobody from our team is free right now. They will reply in the chat on this website.', [], $locale),
        };

        $message = $conversation->messages()->create([
            'role'  => ShoppingAssistantMessageRoleEnum::ASSISTANT,
            'body'  => $body,
            'model' => 'hand-over',
            'flags' => ['handed_over'],
        ]);
        $conversation->increment('messages_count', 1, ['last_message_at' => now()]);

        return $message;
    }

    private function validatedOffer(ShoppingAssistantConversation $conversation, ?ShoppingAssistantMessage $offerMessage): ShoppingAssistantMessage
    {
        if (!$offerMessage) {
            throw ValidationException::withMessages(['message' => __('Please write your question for our team.')]);
        }

        if ($offerMessage->shopping_assistant_conversation_id !== $conversation->id
            || $offerMessage->role !== ShoppingAssistantMessageRoleEnum::ASSISTANT
            || !$offerMessage->hand_over_offer
            || !$this->latestCustomerMessageBefore($conversation, $offerMessage)) {
            throw ValidationException::withMessages(['offer_message_id' => __('That answer does not offer a person.')]);
        }

        return $offerMessage;
    }

    private function questionFor(ShoppingAssistantConversation $conversation, ShoppingAssistantMessage $offerMessage): ShoppingAssistantMessage
    {
        return $this->latestCustomerMessageBefore($conversation, $offerMessage);
    }

    private function latestCustomerMessageBefore(ShoppingAssistantConversation $conversation, ShoppingAssistantMessage $offerMessage): ?ShoppingAssistantMessage
    {
        return $conversation->messages()
            ->where('role', ShoppingAssistantMessageRoleEnum::CUSTOMER)
            ->where('id', '<', $offerMessage->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{hand_over: ShoppingAssistantHandOver, message: ShoppingAssistantMessage, chat_session: ChatSession}|null
     */
    private function existingResult(ShoppingAssistantConversation $conversation, ShoppingAssistantMessage $question): ?array
    {
        $handOver = $conversation->handOvers()->where('customer_message_id', $question->id)->first();

        return $handOver ? $this->resultOf($handOver) : null;
    }

    /**
     * What an earlier hand-over gave: its chat and the sentence the customer was told. A chat left
     * with no message at all (the question could not be sent after the hand-over was saved) gets the
     * question now, so a second tap repairs it instead of leaving a chat the inbox never lists.
     *
     * @return array{hand_over: ShoppingAssistantHandOver, message: ShoppingAssistantMessage, chat_session: ChatSession}|null
     */
    private function resultOf(ShoppingAssistantHandOver $handOver): ?array
    {
        $session = $handOver->chatSession;
        $message = $session ? $handOver->conversation->messages()
            ->where('role', ShoppingAssistantMessageRoleEnum::ASSISTANT)
            ->where('id', '>', (int) $handOver->customer_message_id)
            ->orderBy('id')
            ->get()
            ->first(fn (ShoppingAssistantMessage $candidate) => in_array('handed_over', Arr::wrap($candidate->flags), true)) : null;

        if (!$session || !$message) {
            return null;
        }

        if (!$session->messages()->exists()) {
            $this->sendQuestion($session, $handOver);
        }

        return ['hand_over' => $handOver, 'message' => $message, 'chat_session' => $session];
    }
}
