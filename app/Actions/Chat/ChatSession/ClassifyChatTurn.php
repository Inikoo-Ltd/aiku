<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Helpers\AI\AskJev;
use App\Actions\Iris\Docs\ShowIrisDocs;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Events\BroadcastChatAiDraft;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the customer wants from what they wrote since our last message, worked out like a game
 * of "Who am I?": Jev answers yes/no questions and picks from fixed options, each with its own
 * probability, and code walks down from the answers. The first round finds the area (an order,
 * stock, a product, a problem, a dropshipping store); the second asks only that area's
 * questions. For a dropshipping store the options are the website's own /docs guides, so a
 * draft can point to the guide that answers it. Code, never the model, turns the answers into
 * the topic a draft may answer, and anything short of sure is left to an agent. Every answer is
 * kept on the conversation so a wrong turn can be traced to the question that took it.
 *
 * The same answers feed everything read from a customer's message: whether to draft, the
 * urgent flag for a cancel or change of address, the claim checklist out of hours, closing
 * after a thanks, the dropshipping queue and the guide an agent may send.
 */
class ClassifyChatTurn
{
    use AsAction;

    public const string KEY = 'ai_turn';

    private const string CONTEXT = 'Customer service of a wholesale giftware supplier. "customer_wrote" is what the customer wrote since our last message "we_said"; emails can quote older messages below the new text, judge only the new text.';

    private const float SURE = 0.85;

    private const float LIKELY = 0.7;

    private const float UNLIKELY = 0.3;

    private const float URGENT = 0.4;

    private const array CLAIM_KINDS = ['missing', 'damaged', 'wrong_item'];

    /**
     * The turn of the customer's latest message, asked once: the urgent flag, the out of hours
     * claim check and the draft all run on the same message at the same time, and whichever
     * comes first asks Jev for the others.
     *
     * @return array{branch: string|null, topic: ChatTopicEnum|null, answers: array<string, mixed>, guide: array<string, string>|null, hint: array<string, mixed>|null, urgent: string|null, closing: float, claim: bool, ds_kind: string|null}|null null when there is nothing to read or Jev could not be asked
     */
    public static function forSession(ChatSession|MetaChatSession $chatSession): ?array
    {
        $latestId = $chatSession->messages()->whereIn('sender_type', [ChatSenderTypeEnum::USER, ChatSenderTypeEnum::GUEST])->max('id');
        $text     = self::customerWrote($chatSession);

        if (!$latestId || $text === '') {
            return null;
        }

        $key  = 'chat-turn:'.class_basename($chatSession).':'.$chatSession->id.':'.$latestId;
        $turn = Cache::get($key);

        if ($turn === null) {
            $turn = self::run($chatSession, $text, self::weSaid($chatSession));

            if ($turn !== null) {
                Cache::put($key, $turn, now()->addMinutes(30));
            }
        }

        return $turn;
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
     * @return array{branch: string|null, topic: ChatTopicEnum|null, answers: array<string, mixed>, guide: array<string, string>|null, hint: array<string, mixed>|null, urgent: string|null, closing: float, claim: bool, ds_kind: string|null}|null null when Jev could not be asked
     */
    public function handle(ChatSession|MetaChatSession $chatSession, string $customerWrote, string $weSaid): ?array
    {
        $isDropship = $chatSession->shop?->type === ShopTypeEnum::DROPSHIPPING;
        $state      = ['we_said' => mb_substr($weSaid, 0, 1500), 'customer_wrote' => mb_substr($customerWrote, 0, 4000)];
        $first      = AskJev::make()->handle($state, self::firstRound($isDropship));

        if (!$first) {
            return null;
        }

        $branch  = self::branch($first);
        $guides  = $branch === 'integration' ? self::guides($chatSession) : [];
        $round   = $branch ? self::secondRound($branch, $guides) : [];
        $second  = $round ? (AskJev::make()->handle($state, $round) ?? []) : [];
        $answers = array_merge($first, $second);
        $topic   = self::topic($branch, $answers);
        $picked  = (string) Arr::get($answers, 'guide.choice');
        $guide   = $guides[$picked] ?? null;
        $hint    = $guide && self::sureOf($answers, 'guide', $picked) ? $guide + [
            'probability' => round((float) Arr::get($answers, "guide.probabilities.$picked"), 2),
            'message'     => self::guideMessage($guide, $chatSession->shop?->language?->code),
        ] : null;

        if ($topic === ChatTopicEnum::DROPSHIPPING_INTEGRATION && !$guide) {
            $topic = null;
        }

        $turn = [
            'branch'  => $branch,
            'topic'   => $topic,
            'answers' => $answers,
            'guide'   => $topic ? $guide : null,
            'hint'    => $hint,
            'urgent'  => self::urgent($answers),
            'closing' => (float) Arr::get($answers, 'act.probabilities.closing', 0),
            'claim'   => $branch === 'problem' && in_array(Arr::get($answers, 'problem_kind.choice'), self::CLAIM_KINDS, true),
            'ds_kind' => $isDropship ? self::dsKind($answers) : null,
        ];

        $metadata            = $chatSession->metadata ?? [];
        $metadata[self::KEY] = [
            'at'      => now()->toISOString(),
            'branch'  => $branch,
            'topic'   => $topic?->value,
            'urgent'  => $turn['urgent'],
            'claim'   => $turn['claim'],
            'hint'    => $hint,
            'answers' => self::compact($answers),
        ];
        $chatSession->update(['metadata' => $metadata]);

        if ($hint) {
            BroadcastChatAiDraft::dispatch($chatSession, null);
        }

        return $turn;
    }

    /**
     * What "Insert link" puts in the reply, in the shop's language like the guide itself.
     *
     * @param  array<string, string>  $guide
     */
    public static function guideMessage(array $guide, ?string $locale): string
    {
        return __('We have a guide that explains this step by step, I hope it helps:', [], $locale)."\n".$guide['title']."\n".$guide['url']."\n\n"
            .__('If anything is still unclear, just let us know and we will be happy to help.', [], $locale);
    }

    /**
     * The guide an agent may send, while nobody has answered since it was found.
     *
     * @return array<string, mixed>|null
     */
    public static function currentHint(ChatSession|MetaChatSession $chatSession): ?array
    {
        $turn = data_get($chatSession->metadata, self::KEY);

        if (!is_array($turn) || empty($turn['hint']) || empty($turn['at'])) {
            return null;
        }

        $answeredAt = $chatSession->last_agent_message_at;

        return !$answeredAt || Carbon::parse($answeredAt)->lt(Carbon::parse($turn['at'])) ? $turn['hint'] : null;
    }

    /**
     * Cancel or change of address, flagged on even odds: a missed one ships, a wrong one costs
     * an agent a glance.
     *
     * @param  array<string, mixed>  $answers
     */
    public static function urgent(array $answers): ?string
    {
        $cancel  = (float) Arr::get($answers, 'request.probabilities.cancel_order', 0);
        $address = (float) Arr::get($answers, 'request.probabilities.change_address', 0);

        return match (true) {
            max($cancel, $address) < self::URGENT => null,
            $cancel >= $address                   => 'cancel_order',
            default                               => 'change_address',
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

    /**
     * The /docs guides of a dropshipping shop's website, in its language, by slug.
     *
     * @return array<string, array<string, string>>
     */
    public static function guides(ChatSession|MetaChatSession $chatSession): array
    {
        $shop = $chatSession->shop;

        if ($shop?->type !== ShopTypeEnum::DROPSHIPPING || !$shop->website?->domain) {
            return [];
        }

        return collect(ShowIrisDocs::make()->handle($shop->website))
            ->mapWithKeys(fn (array $doc) => [$doc['slug'] => [
                'title'   => $doc['title'],
                'summary' => $doc['summary'],
                'url'     => 'https://'.ltrim($shop->website->domain, '/').$doc['url'],
            ]])
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
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
            'answers_us'           => self::noul('Is this mainly a reply to something we just asked or offered?', 'It mainly answers our question or offer', 'It is not a reply to a question or offer of ours'),
            'about_existing_order' => self::noul('Is it about an order they already placed?', 'It is about an order already placed, its delivery or its invoice', 'It is not about an order already placed'),
            'problem'              => self::noul('Are they reporting something wrong?', 'Something is missing, damaged, wrong, late, lost, not received, or charged or shown wrongly', 'Nothing is reported as wrong'),
            'one_question'         => self::noul('Is there exactly one thing they want, and nothing else?', 'Exactly one simple question or request', 'Several things, a negotiation, or nothing specific'),
            'still_waiting'        => self::noul('Are they chasing something we promised them?', 'They chase a reply, refund, check or action we promised', 'They are not chasing a promise of ours'),
            'subject'              => self::choice('What is the message about?', [
                'order'       => 'An order they placed: where it is, its contents, tracking, changes',
                'stock'       => 'Whether a product is in stock or when it comes back',
                'product'     => 'What a product is: size, ingredients, origin, documents',
                'price'       => 'Prices, discounts, quotes',
                'shop'        => 'How the shop works: minimum order, delivery costs, countries or times before ordering, opening an account, samples',
                'emails'      => 'Stopping our newsletters or marketing emails',
                'payment'     => 'A payment, invoice, VAT or balance',
                'website'     => 'The website, login or checkout not working',
                'integration' => 'Dropshipping store connections, Shopify, eBay, API, feeds',
                'other'       => 'None of these: thanks, sourcing offers, notifications, anything else',
            ]),
            'request'              => self::choice('Do they ask us to cancel an order or change its delivery address?', [
                'cancel_order'   => 'They ask us to cancel an order they placed, or part of it, before it ships',
                'change_address' => 'They ask to change or correct the delivery address of an order they placed',
                'none'           => 'Neither of these',
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
     * @param  array<string, array<string, string>>  $guides
     * @return array<string, array<string, mixed>>
     */
    public static function secondRound(string $branch, array $guides = []): array
    {
        return match ($branch) {
            'integration' => $guides ? [
                'how_to' => self::noul('Do they ask how to do something, rather than tell us something is not working?', 'They ask how to connect, set up or do something', 'They report an error or something not working, or ask something else'),
                'guide'  => self::choice('Which of our guides answers what they ask?', [
                    ...collect($guides)->map(fn (array $guide) => $guide['title'].': '.$guide['summary'])->all(),
                    'none' => 'None of these guides answers it',
                ]),
            ] : [],
            'problem' => [
                'problem_kind' => self::choice('What went wrong?', [
                    'missing'         => 'Items missing from a delivery',
                    'damaged'         => 'Items arrived damaged or faulty',
                    'wrong_item'      => 'The wrong item was sent',
                    'not_arrived'     => 'The order or parcel has not arrived, is late or lost',
                    'charged_wrongly' => 'A charge, invoice, balance or status looks wrong',
                    'other'           => 'Something else',
                ]),
                'names_order'  => self::noul('Do they give an order number?', 'An order number is written in the message', 'No order number is written'),
            ],
            'order' => [
                'order_ask'   => self::choice('What do they want to know or have done about their order?', [
                    'where_is_it'        => 'Where it is, whether it has shipped, when it ships or arrives',
                    'tracking'           => 'The tracking number or link',
                    'replacement_parcel' => 'A replacement, resend or second parcel',
                    'contents'           => 'What is in it or what was not sent',
                    'change_or_cancel'   => 'Change, add to, cancel it or change its address',
                    'hurry'              => 'Send it faster or by a date',
                    'other'              => 'Something else',
                ]),
                'names_order' => self::noul('Do they give an order number?', 'An order number is written in the message', 'No order number is written'),
            ],
            'stock' => [
                'stock_ask'          => self::choice('What do they want to know about the product?', [
                    'in_stock_now' => 'Whether it is in stock now, or how many we have',
                    'when_back'    => 'When it will be back in stock',
                    'alternative'  => 'An alternative to a product that is out of stock',
                    'bulk'         => 'A large quantity, sourcing more than we hold, or reserving stock',
                    'price'        => 'Its price or a discount',
                    'other'        => 'Something else',
                ]),
                'names_product_code' => self::noul('Do they name the product by its code or a link to it?', 'A product code or a link to the product is in the message', 'The product is only described, or not named'),
            ],
            'product' => [
                'product_need'       => self::choice('What do they need to know about the product?', [
                    'size_weight_origin' => 'Its size, weight, what it is made of or where it comes from',
                    'document'           => 'A safety or compliance document: CPSR, SDS, certificate, ingredient list',
                    'law_or_labels'      => 'Whether they need labels or what the law asks of them',
                    'how_to_use'         => 'How to use it',
                    'other'              => 'Something else',
                ]),
                'names_product_code' => self::noul('Do they name the product by its code or a link to it?', 'A product code or a link to the product is in the message', 'The product is only described, or not named'),
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    public static function branch(array $answers): ?string
    {
        if (self::yes($answers, 'wants_something') < 0.5) {
            return null;
        }

        if (self::yes($answers, 'problem') >= 0.5 && self::yes($answers, 'about_existing_order') >= 0.5) {
            return 'problem';
        }

        $subject = Arr::get($answers, 'subject.choice');

        return in_array($subject, ['order', 'stock', 'product', 'integration'], true) ? $subject : null;
    }

    /**
     * The topic a draft may answer, only when every answer points the same way; otherwise null
     * and an agent answers.
     *
     * @param  array<string, mixed>  $answers
     */
    public static function topic(?string $branch, array $answers): ?ChatTopicEnum
    {
        $subject = Arr::get($answers, 'subject.choice');

        $clearAsk = self::yes($answers, 'wants_something') >= self::SURE
            && self::yes($answers, 'one_question') >= self::LIKELY
            && self::yes($answers, 'answers_us') < self::UNLIKELY
            && self::yes($answers, 'problem') < self::UNLIKELY
            && self::yes($answers, 'still_waiting') < self::UNLIKELY
            && self::sureOf($answers, 'subject', (string) $subject);

        if (!$clearAsk) {
            return null;
        }

        return match (true) {
            $branch === 'order'
                && self::sureOfOneOf($answers, 'order_ask', ['where_is_it', 'tracking', 'contents'])     => ChatTopicEnum::ORDER_STATUS,
            $branch === 'stock'
                && self::sureOfOneOf($answers, 'stock_ask', ['in_stock_now', 'when_back', 'alternative'])
                && self::yes($answers, 'names_product_code') >= self::LIKELY                             => ChatTopicEnum::STOCK_AVAILABILITY,
            $branch === 'product'
                && self::sureOfOneOf($answers, 'product_need', ['size_weight_origin'])
                && self::yes($answers, 'names_product_code') >= self::LIKELY                             => ChatTopicEnum::PRODUCT_QUERY,
            $branch === 'integration'
                && self::yes($answers, 'how_to') >= self::SURE
                && Arr::get($answers, 'guide.choice') !== 'none'
                && self::sureOf($answers, 'guide', (string) Arr::get($answers, 'guide.choice'))           => ChatTopicEnum::DROPSHIPPING_INTEGRATION,
            $branch === null && in_array($subject, ['shop', 'emails'], true)                            => ChatTopicEnum::OTHER,
            default                                                                                      => null,
        };
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
    private static function sureOf(array $answers, string $question, string $option): bool
    {
        return (float) Arr::get($answers, "$question.probabilities.$option", 0) >= self::LIKELY;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<int, string>  $options
     */
    private static function sureOfOneOf(array $answers, string $question, array $options): bool
    {
        return in_array(Arr::get($answers, "$question.choice"), $options, true)
            && self::sureOf($answers, $question, (string) Arr::get($answers, "$question.choice"));
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
