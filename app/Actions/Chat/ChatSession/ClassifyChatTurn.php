<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Helpers\AI\AskJev;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\ChatTurnReading;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the customer wants from what they wrote since our last message: Jev answers yes/no
 * questions and picks from fixed options, each with its own probability, and code walks down
 * from the answers. A problem with an order they placed gets a second round asking what went
 * wrong. The answers feed the urgent flag for a cancel or change of address, the claim checklist
 * out of hours, closing after a thanks and the dropshipping queue. Every answer is kept on the
 * conversation so a wrong turn can be traced to the question that took it, and each reading is
 * kept beside the agent's reply for LearnChatKnowledge.
 */
class ClassifyChatTurn
{
    use AsAction;

    public const string KEY = 'ai_turn';

    private const string CONTEXT = 'Customer service of a wholesale giftware supplier. "customer_wrote" is what the customer wrote since our last message "we_said"; emails can quote older messages below the new text, judge only the new text.';

    private const float LIKELY = 0.7;

    private const float URGENT = 0.4;

    private const int LOCK_SECONDS = 150;

    private const int WAIT_SECONDS = 40;

    private const array CLAIM_KINDS = ['missing', 'damaged', 'wrong_item'];

    /**
     * The turn of the customer's latest message, asked once: the urgent flag and the out of hours
     * claim check run on the same message at the same time, and whichever comes first asks Jev
     * for the other.
     *
     * @return array{branch: string|null, answers: array<string, mixed>, urgent: string|null, closing: float, claim: bool, names_items: bool, ds_kind: string|null}|null null when there is nothing to read or Jev could not be asked
     */
    public static function forSession(ChatSession|MetaChatSession $chatSession): ?array
    {
        $latest   = $chatSession->messages()->whereIn('sender_type', [ChatSenderTypeEnum::USER, ChatSenderTypeEnum::GUEST])->latest('id')->first(['id', 'metadata']);
        $latestId = $latest?->id;
        $text     = self::customerWrote($chatSession);

        if (!$latestId || $text === '' || data_get($latest->metadata, 'auto_reply')) {
            return null;
        }

        $key  = 'chat-turn:'.class_basename($chatSession).':'.$chatSession->id.':'.$latestId;
        $turn = Cache::get($key);

        if ($turn !== null) {
            return $turn;
        }

        try {
            return Cache::lock($key.':lock', self::LOCK_SECONDS)->block(self::WAIT_SECONDS, function () use ($chatSession, $key, $text, $latestId) {
                $turn = Cache::get($key);

                if ($turn === null) {
                    $turn = self::run($chatSession, $text, self::weSaid($chatSession), $latestId);

                    if ($turn !== null) {
                        Cache::put($key, $turn, now()->addMinutes(30));
                    }
                }

                return $turn;
            });
        } catch (LockTimeoutException) {
            return Cache::get($key);
        }
    }

    /**
     * What the customer wrote since our last message, and since the conversation was last closed.
     */
    public static function customerWrote(ChatSession|MetaChatSession $chatSession): string
    {
        $lastClosedId = $chatSession->messages()
            ->where('sender_type', ChatSenderTypeEnum::SYSTEM->value)
            ->where('message_text', 'like', 'Chat session has been closed by %')
            ->max('id');

        return $chatSession->messages()
            ->whereIn('sender_type', [ChatSenderTypeEnum::USER->value, ChatSenderTypeEnum::GUEST->value])
            ->when($chatSession->last_agent_message_at, fn ($query, $answeredAt) => $query->where('created_at', '>', $answeredAt))
            ->when($lastClosedId, fn ($query, $closedId) => $query->where('id', '>', $closedId))
            ->orderBy('created_at')
            ->get()
            ->map(fn ($message) => trim((string) ($message->original_text ?? $message->message_text ?? '')))
            ->filter()
            ->join("\n\n");
    }

    public static function weSaid(ChatSession|MetaChatSession $chatSession): string
    {
        $message = $chatSession->messages()->where('sender_type', ChatSenderTypeEnum::AGENT)->latest('id')->first();

        return mb_substr(trim((string) ($message?->message_text ?? '')), 0, 1500) ?: '(nothing yet)';
    }

    /**
     * @return array{branch: string|null, answers: array<string, mixed>, urgent: string|null, closing: float, claim: bool, names_items: bool, ds_kind: string|null}|null null when Jev could not be asked
     */
    public function handle(ChatSession|MetaChatSession $chatSession, string $customerWrote, string $weSaid, ?int $customerMessageId = null): ?array
    {
        $isDropship = $chatSession->shop?->type === ShopTypeEnum::DROPSHIPPING;
        $state      = ['we_said' => mb_substr($weSaid, 0, 1500), 'customer_wrote' => mb_substr($customerWrote, 0, 4000)];
        $first      = AskJev::make()->handle($state, self::firstRound($isDropship));

        if (!$first) {
            return null;
        }

        $branch  = self::branch($first);
        $second  = $branch === 'problem' ? (AskJev::make()->handle($state, self::problemRound()) ?? []) : [];
        $answers = array_merge($first, $second);

        $claim = $branch === 'problem' && in_array(Arr::get($answers, 'problem_kind.choice'), self::CLAIM_KINDS, true);

        $turn = [
            'branch'      => $branch,
            'answers'     => $answers,
            'urgent'      => self::urgent($answers),
            'closing'     => (float) Arr::get($answers, 'act.probabilities.closing', 0),
            'claim'       => $claim,
            'names_items' => $claim && self::yes($answers, 'names_items') >= 0.5,
            'ds_kind'     => $isDropship ? self::dsKind($answers) : null,
        ];

        $reading = $chatSession->shop
            ? ChatTurnReading::createOrFirst([
                'chat_session_id'      => $chatSession instanceof ChatSession ? $chatSession->id : null,
                'meta_chat_session_id' => $chatSession instanceof MetaChatSession ? $chatSession->id : null,
                'customer_message_id'  => $customerMessageId,
            ], [
                'group_id'        => $chatSession->shop->group_id,
                'organisation_id' => $chatSession->shop->organisation_id,
                'shop_id'         => $chatSession->shop_id,
                'customer_wrote'  => mb_substr($customerWrote, 0, 2000),
                'branch'          => $branch,
            ])
            : null;

        $messageAt = $customerMessageId ? $chatSession->messages()->whereKey($customerMessageId)->value('created_at') : null;

        SetChatSessionMetadata::run($chatSession, [self::KEY => [
            'at'         => now()->toISOString(),
            'message_at' => $messageAt ? Carbon::parse($messageAt)->toISOString() : null,
            'reading_id' => $reading?->id,
            'branch'     => $branch,
            'urgent'     => $turn['urgent'],
            'claim'      => $turn['claim'],
            'answers'    => self::compact($answers),
        ]]);

        return $turn;
    }

    /**
     * The agent's first reply after a reading, kept beside what was suggested so the two can be
     * compared: it is how the questions, thresholds and queries get better.
     */
    public static function recordReply(ChatSession|MetaChatSession $chatSession, ChatMessage|MetaChatMessage $reply): void
    {
        ChatTurnReading::where($chatSession instanceof ChatSession ? 'chat_session_id' : 'meta_chat_session_id', $chatSession->id)
            ->whereNull('replied_at')
            ->latest('id')
            ->first()
            ?->update([
                'reply_message_id' => $reply->id,
                'reply'            => mb_substr((string) $reply->message_text, 0, 2000),
                'replied_at'       => now(),
            ]);
    }

    /**
     * Cancel, change of address or a delivery instruction, flagged on even odds: a missed one
     * ships, a wrong one costs an agent a glance. Cancelling wins over the address, and the
     * address over an instruction for the same address. Cancelling it all, taking items off and
     * holding it are one request to stop the order as it is. Agreeing to leave out what we said
     * is out of stock is not: the order ships as we proposed.
     *
     * @param  array<string, mixed>  $answers
     */
    public static function urgent(array $answers): ?string
    {
        $cancel      = array_sum(array_map(fn (string $option) => (float) Arr::get($answers, "request.probabilities.$option", 0), ['cancel_all', 'remove_items', 'hold']));
        $address     = (float) Arr::get($answers, 'request.probabilities.change_address', 0);
        $instruction = (float) Arr::get($answers, 'request.probabilities.delivery_instruction', 0);

        return match (true) {
            max($cancel, $address, $instruction) < self::URGENT => null,
            $cancel >= max($address, $instruction)              => 'cancel_order',
            $address >= $instruction                            => 'change_address',
            default                                             => 'delivery_instruction',
        };
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private static function dsKind(array $answers): ?string
    {
        $kind = Arr::get($answers, 'ds_kind.choice');

        return in_array($kind, ['documents', 'integration'], true) && self::sureOf($answers, 'ds_kind', $kind) ? $kind : null;
    }

    public static function firstRound(bool $isDropship = false): array
    {
        $dropship = $isDropship ? [
            'ds_kind' => self::choice('This customer sells our products in their own online store and we dropship them. What do they need?', [
                'documents'   => 'A document: a safety or compliance document (CPSR, SDS, MSDS, CLP, IFRA, allergen or ingredient lists, certificate of analysis), an invoice or a copy of one, or customs paperwork',
                'integration' => 'Technical help with how their store or marketplace is connected to us: Shopify, WooCommerce, Wix, eBay, TikTok, Amazon, Faire or the API, products not syncing, orders not coming through',
                'cs'          => 'Anything else',
            ]),
        ] : [];

        return $dropship + [
            'wants_something'      => self::noul('Are they asking us for something now?', 'They ask a question or ask us to do or send something', 'They only thank, inform, confirm or reply, and want nothing new from us'),
            'about_existing_order' => self::noul('Is it about an order they already placed?', 'It is about an order already placed, its delivery or its invoice', 'It is not about an order already placed'),
            'problem'              => self::noul('Are they reporting something wrong?', 'Something is missing, damaged, wrong, late, lost, not received, or charged or shown wrongly', 'Nothing is reported as wrong'),
            'request'              => self::choice('Do they ask us to cancel, change or hold an order they placed?', [
                'cancel_all'           => 'Cancel an order they placed, or not send it at all',
                'remove_items'         => 'Take items they chose off an order they placed, before it ships: added by mistake, too many, changed their mind',
                'hold'                 => 'Stop, hold or delay an order they placed before it ships',
                'change_address'       => 'Change or correct the delivery address of an order they placed, or send it somewhere else',
                'delivery_instruction' => 'Tell us how to deliver an order they placed that has not arrived, at the same address: a safe place, not to leave it unattended, a neighbour, opening hours, call before delivery',
                'accept_ours'          => 'Agree to what we proposed, such as sending it without the items we said are out of stock and refunding those',
                'none'                 => 'None of these: where an order is, adding or swapping items, a return or refund after delivery, the address on their account, anything else',
            ]),
            'act'                  => self::choice("What is the customer's latest message doing?", [
                'closing'   => 'Only thanks, a goodbye or confirming they received something; they want nothing more from us',
                'asking'    => 'They ask a question, make a request, report a problem or complain',
                'pending'   => 'We still owe them something (a refund, a check, a reply we promised) or they are waiting on us',
                'answering' => 'They reply to something we asked or offered, a decision an agent must act on',
                'informing' => 'They give new details, say they paid, sent or will send something, or attach something',
            ]),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function problemRound(): array
    {
        return [
            'problem_kind' => self::choice('What went wrong?', [
                'missing'         => 'Items missing from a delivery',
                'damaged'         => 'Items arrived damaged or faulty',
                'wrong_item'      => 'The wrong item was sent',
                'not_arrived'     => 'The order or parcel has not arrived, is late or lost',
                'charged_wrongly' => 'A charge, invoice, balance or status looks wrong',
                'other'           => 'Something else',
            ]),
            'names_items'  => self::noul(
                'Does the customer say which products are affected and how many of each?',
                'They name the products (by name, code or description) and give the quantities affected, such as "2 of the 6 Moonstone candles arrived broken"',
                'They do not say which products, or not how many of them'
            ),
        ];
    }

    /**
     * A problem with an order is a claim even when the customer only reports it: "nine lamps
     * arrived broken, photos attached" asks for nothing in words, and Jev then doubts they want
     * something, but reporting it is the request.
     *
     * @param  array<string, mixed>  $answers
     */
    public static function branch(array $answers): ?string
    {
        return self::yes($answers, 'problem') >= 0.5 && self::yes($answers, 'about_existing_order') >= 0.5
            && (self::yes($answers, 'wants_something') >= 0.5 || Arr::get($answers, 'act.choice') === 'asking')
            ? 'problem'
            : null;
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private static function yes(array $answers, string $question): float
    {
        return (float) Arr::get($answers, "$question.noul", 0);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private static function sureOf(array $answers, string $question, string $option, float $atLeast = self::LIKELY): bool
    {
        return (float) Arr::get($answers, "$question.probabilities.$option", 0) >= $atLeast;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private static function compact(array $answers): array
    {
        return collect($answers)->map(fn ($answer) => isset($answer['noul'])
            ? round((float) $answer['noul'], 2)
            : ['choice' => $answer['choice'] ?? null, 'probability' => round((float) Arr::get($answer, 'probabilities.'.($answer['choice'] ?? ''), 0), 2)])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function noul(string $question, string $yes, string $no): array
    {
        return ['type' => 'noul', 'instructions' => self::CONTEXT.' '.$question, 'criteria' => ['true' => $yes, 'false' => $no]];
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    private static function choice(string $question, array $options): array
    {
        return ['type' => 'choice', 'instructions' => self::CONTEXT.' '.$question, 'criteria' => $options];
    }
}
