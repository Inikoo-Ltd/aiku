<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 01:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Actions\Ordering\Order\CheckClaimCompensation;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\Ordering\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What a customer reporting a problem has already given us since a moment: an order number
 * that is really an order, and how many photos or files. Read from what they sent, email
 * subjects included, never from what they claim, so the agent sees at a glance what is
 * still missing.
 */
class GetChatClaimDetails
{
    use AsAction;

    /**
     * @return array{order_reference: ?string, customer: ?Customer, photos: int, text: string}
     */
    public function handle(ChatSession|MetaChatSession $chatSession, ?Carbon $since = null): array
    {
        $messages = $chatSession->messages()
            ->whereIn('sender_type', [ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::USER])
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->when($chatSession instanceof ChatSession, fn ($query) => $query->with('media'))
            ->oldest('id')
            ->get();

        $text = $messages
            ->map(fn ($message) => trim((string) ($message->original_text ?? $message->message_text ?? '')))
            ->filter()
            ->join("\n");

        $photos = $messages->filter(function ($message) use ($chatSession) {
            if (in_array($message->message_type, [ChatMessageTypeEnum::IMAGE, ChatMessageTypeEnum::FILE], true)) {
                return true;
            }

            return $chatSession instanceof ChatSession && $message->attachedFiles()->isNotEmpty();
        })->count();

        $subjects = $messages->reverse()
            ->map(fn ($message) => data_get($message->metadata, 'email_subject'))
            ->push(data_get($chatSession->metadata, 'email_subject'))
            ->map(fn ($subject) => trim((string) $subject))
            ->filter()
            ->unique()
            ->join("\n");

        $shop     = $chatSession->shop;
        $customer = self::customer($chatSession);

        return [
            'order_reference' => $shop ? self::orderReference($shop, trim($subjects."\n".$text), $customer) : null,
            'customer'        => $customer,
            'photos'          => $photos,
            'text'            => $text,
        ];
    }

    /**
     * Who is writing: the customer behind the web user or WhatsApp contact, or by email the
     * shop's customer with the sender's address, who often writes before logging in.
     */
    public static function customer(ChatSession|MetaChatSession $chatSession): ?Customer
    {
        if ($chatSession instanceof MetaChatSession) {
            return $chatSession->customer;
        }

        $email = trim((string) data_get($chatSession->metadata, 'email_from'));

        return $chatSession->webUser?->customer
            ?? ($email !== '' && $chatSession->shop ? self::byEmail($chatSession->shop->group_id, $email)->where('shop_id', $chatSession->shop_id)->oldest('id')->first() : null);
    }

    /**
     * Through customers_group_id_identity_email_index: by shop or organisation alone the
     * lookup scans every customer of the largest shops.
     */
    private static function byEmail(int $groupId, string $email): Builder
    {
        return Customer::where('group_id', $groupId)
            ->whereNotNull('email')
            ->whereRaw('lower(trim(email)) = ?', [mb_strtolower(trim($email))]);
    }

    /**
     * What the inbox shows beside a conversation whose customer was asked for claim details
     * and has not been answered since: what has arrived, so the agent sees at a glance whether
     * the case is ready to decide. Nothing for everybody else, and no query for them either.
     *
     * @return array{order_reference: ?string, photos: int}|null
     */
    public static function forList(ChatSession|MetaChatSession $chatSession): ?array
    {
        $askedAt = data_get($chatSession->metadata, SendOutOfHoursReply::CLAIM_KEY);

        if (!$askedAt) {
            return null;
        }

        $answeredAt = $chatSession->last_agent_message_at ? Carbon::parse($chatSession->last_agent_message_at) : null;

        if ($answeredAt?->gt(Carbon::parse($askedAt))) {
            return null;
        }

        $details = self::run($chatSession, $answeredAt);

        return ['order_reference' => $details['order_reference'], 'photos' => $details['photos']];
    }

    /**
     * Customers write the number with the shop's letters or without them, glued to a word
     * ("OrderGB591846"), spaced ("AWD - 201237") or as bare digits, so a known customer's
     * number is matched on its digits against their own orders, in this shop or, under the
     * same email, in another shop of the organisation they wrote to by mistake. Their own
     * shop wins, then the number written first. Bare digits are too often a phone number, a
     * price or a stranger's order, so a guest's number counts only written in full with its
     * letters, and only in this shop. A year is never an order number.
     */
    public static function orderReference(Shop $shop, string $text, ?Customer $customer = null): ?string
    {
        preg_match_all('/([A-Za-z]{0,7})([\s#-]*)(?<!\d)(\d{4,9})\b/u', $text, $matches, PREG_SET_ORDER);

        $digits  = [];
        $written = [];

        foreach ($matches as [, $letters, $separator, $number]) {
            if (preg_match('/^(19|20)\d\d$/', $number)) {
                continue;
            }

            $digits[] = $number;
            $words    = $separator === '' ? array_map(fn (int $length) => substr($letters, -$length), range(min(5, strlen($letters)), 2)) : [$letters];

            foreach (array_filter($words, fn (string $word) => strlen($word) >= 2) as $word) {
                array_push($written, $word.$number, strtoupper($word).$number, $word.'-'.$number, strtoupper($word).'-'.$number);
            }
        }

        if (!$digits) {
            return null;
        }

        $digits = array_values(array_unique($digits));
        $rank   = array_flip($digits);

        if (!$customer) {
            return Order::where('shop_id', $shop->id)
                ->whereIn('reference', array_values(array_unique($written)) ?: [''])
                ->get(['reference'])
                ->sortBy(fn (Order $order) => $rank[preg_replace('/^\D+/', '', $order->reference)] ?? PHP_INT_MAX)
                ->first()?->reference;
        }

        $customerIds = $customer->email
            ? self::byEmail($shop->group_id, $customer->email)->where('organisation_id', $shop->organisation_id)->pluck('id')->push($customer->id)->unique()->all()
            : [$customer->id];

        return Order::whereIn('customer_id', $customerIds)
            ->whereIn(DB::raw("regexp_replace(reference, '^\\D+', '')"), $digits)
            ->get(['reference', 'shop_id'])
            ->sortBy([
                fn (Order $a, Order $b) => ($a->shop_id !== $shop->id) <=> ($b->shop_id !== $shop->id),
                fn (Order $a, Order $b) => ($rank[preg_replace('/^\D+/', '', $a->reference)] ?? PHP_INT_MAX) <=> ($rank[preg_replace('/^\D+/', '', $b->reference)] ?? PHP_INT_MAX),
            ])
            ->first()?->reference;
    }

    /**
     * The order a customer who gave no number most likely means: their only order dispatched
     * within the claim window. Never more than a guess to put to them, so it is asked, not taken.
     */
    public static function onlyRecentOrder(Customer $customer): ?string
    {
        $references = $customer->orders()
            ->where('state', OrderStateEnum::DISPATCHED)
            ->where('dispatched_at', '>=', now()->subDays(CheckClaimCompensation::MAX_AGE_DAYS))
            ->limit(2)
            ->pluck('reference');

        return $references->count() === 1 ? $references->first() : null;
    }
}
