<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatIntentEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What an agent should know about a linked customer before answering, counted from our own
 * tables, most important first (INI-520). Complaints are the greater of two counts over 7 days:
 * conversations with a draft whose intent is damaged, missing, wrong, delivery failed or a
 * service complaint, and claims raised, so a problem that is both a chat and a claim is not
 * counted twice.
 */
class GetChatCustomerSignals
{
    use AsAction;

    public const COMPLAINT_DAYS = 7;
    public const TOP_CUSTOMER_PERCENT = 2;
    public const LONG_STANDING_YEARS = 3;

    public const COMPLAINT_INTENTS = [
        ChatIntentEnum::DAMAGED_ITEM,
        ChatIntentEnum::MISSING_ITEM,
        ChatIntentEnum::WRONG_ITEM,
        ChatIntentEnum::DELIVERY_FAILED,
        ChatIntentEnum::COMPLAINT_SERVICE,
    ];

    /**
     * @return array<int, array{key: string, label: string, count: int|null, tone: string}>
     */
    public function handle(ChatSession $chatSession): array
    {
        $customer = $chatSession->webUser?->customer;
        if (!$customer) {
            return [];
        }

        $signals = [];

        $complaints = $this->complaintsThisWeek($customer);
        if ($complaints > 0) {
            $signals[] = ['key' => 'complaints', 'label' => __(':ordinal complaint this week', ['ordinal' => $this->ordinal($complaints)]), 'count' => $complaints, 'tone' => 'red'];
        }

        $emails = $this->emailsToday($customer);
        if ($emails > 1) {
            $signals[] = ['key' => 'emails_today', 'label' => __(':ordinal email today', ['ordinal' => $this->ordinal($emails)]), 'count' => $emails, 'tone' => 'amber'];
        }

        $stats = $customer->stats;
        if ((int) $stats?->number_orders === 1) {
            $signals[] = ['key' => 'first_order', 'label' => __("First order: don't lose them"), 'count' => null, 'tone' => 'green'];
        }

        if ($stats && $this->isTopCustomer($customer, (float) $stats->sales_all)) {
            $signals[] = ['key' => 'top_customer', 'label' => __('Top customer'), 'count' => null, 'tone' => 'green'];
        } elseif ($customer->registered_at && ($years = (int) Carbon::parse($customer->registered_at)->diffInYears(now())) >= self::LONG_STANDING_YEARS) {
            $signals[] = ['key' => 'long_standing', 'label' => __('Customer for :years years', ['years' => $years]), 'count' => $years, 'tone' => 'gray'];
        }

        return $signals;
    }

    private function sessionIds(Customer $customer)
    {
        return ChatSession::query()->whereIn('web_user_id', $customer->webUsers()->select('id'))->select('id');
    }

    private function complaintsThisWeek(Customer $customer): int
    {
        $since = now()->subDays(self::COMPLAINT_DAYS);

        $chats = ChatAiDraft::query()
            ->whereIn('chat_session_id', $this->sessionIds($customer))
            ->whereIn('intent', array_map(fn (ChatIntentEnum $intent) => $intent->value, self::COMPLAINT_INTENTS))
            ->where('created_at', '>=', $since)
            ->distinct()
            ->count('chat_session_id');

        $claims = CustomerClaim::query()->where('customer_id', $customer->id)->where('created_at', '>=', $since)->count();

        return max($chats, $claims);
    }

    private function emailsToday(Customer $customer): int
    {
        return ChatMessage::query()
            ->whereIn('chat_session_id', $this->sessionIds($customer)->where('channel', ChatChannelEnum::EMAIL->value))
            ->whereIn('sender_type', [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value])
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    private function isTopCustomer(Customer $customer, float $sales): bool
    {
        if ($sales <= 0) {
            return false;
        }

        $inShop = Customer::query()->where('shop_id', $customer->shop_id);
        $total  = (clone $inShop)->count();
        $above  = (clone $inShop)->whereHas('stats', fn ($query) => $query->where('sales_all', '>', $sales))->count();

        return $above < max(1, (int) floor($total * self::TOP_CUSTOMER_PERCENT / 100));
    }

    private function ordinal(int $number): string
    {
        $suffix = in_array($number % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$number % 10] ?? 'th');

        return $number.$suffix;
    }
}
