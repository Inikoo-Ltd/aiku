<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sep 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A third of the guests on the website chat are customers who were not logged in, and what
 * they typed already says who: the email on the form, the number they write from, an order
 * number in the message. This looks that up and tells the agent who it probably is.
 *
 * It only ever suggests. Anybody can type somebody else's email, and a conversation linked to a
 * customer shows that customer's orders to the agent, who may then read them out. So the link
 * itself is made by an agent's click, in the same shop only. What is found elsewhere, in a
 * sister shop or on the same company domain, is shown as a hint that cannot be confirmed.
 */
class SuggestChatSessionCustomer
{
    use AsAction;

    public string $jobQueue = 'analytics';
    public int $jobTries = 1;

    public const string BASIS_EMAIL = 'email';
    public const string BASIS_PHONE = 'phone';
    public const string BASIS_ORDER = 'order';
    public const string BASIS_MANUAL = 'manual';
    public const string BASIS_PREVIOUS_LINK = 'previous_link';

    public const array FREE_MAIL_DOMAINS = [
        'gmail.com', 'googlemail.com', 'hotmail.com', 'hotmail.co.uk', 'outlook.com', 'live.com', 'live.co.uk', 'msn.com',
        'yahoo.com', 'yahoo.co.uk', 'yahoo.de', 'yahoo.fr', 'yahoo.es', 'icloud.com', 'me.com', 'aol.com', 'btinternet.com',
        'sky.com', 'talktalk.net', 'virginmedia.com', 'protonmail.com', 'proton.me', 'gmx.de', 'gmx.net', 'web.de',
        'seznam.cz', 'centrum.cz', 'azet.sk', 'zoznam.sk', 'wp.pl', 'o2.pl', 'interia.pl', 'orange.fr', 'free.fr', 'libero.it',
    ];

    public function handle(ChatSession|MetaChatSession $chatSession): ChatSession|MetaChatSession
    {
        if (!self::isOpenToSuggestion($chatSession)) {
            return $chatSession;
        }

        $email = strtolower(trim((string) data_get($chatSession->metadata, 'email')));
        $phone = preg_replace('/\D/', '', (string) ($chatSession instanceof MetaChatSession ? $chatSession->phone_number : data_get($chatSession->metadata, 'phone')));

        [$customer, $basis] = $this->inTheSameShop($chatSession, $email, $phone);

        if ($customer && ($chatSession instanceof MetaChatSession || $customer->webUsers()->exists())) {
            $chatSession->update([
                'suggested_customer_id' => $customer->id,
                'suggestion_basis'      => $basis,
            ]);

            return $chatSession;
        }

        $hint = $customer
            ? __('Customer :name, who has no login to link to', ['name' => $customer->name])
            : $this->hint($chatSession, $email, $phone);

        if ($hint && $hint !== $chatSession->suggestion_hint) {
            $chatSession->update(['suggestion_hint' => mb_substr($hint, 0, 250)]);
        }

        return $chatSession;
    }

    public static function isOpenToSuggestion(ChatSession|MetaChatSession $chatSession): bool
    {
        $knownCustomer = $chatSession instanceof ChatSession ? $chatSession->web_user_id : $chatSession->customer_id;

        return !$knownCustomer && !$chatSession->suggested_customer_id && !$chatSession->suggestion_rejected_at && $chatSession->shop_id;
    }

    /**
     * @return array{label: string, basis: ?string, customer: ?array{name: ?string, email: ?string, reference: ?string}, hint: ?string}|null
     */
    public static function forList(ChatSession|MetaChatSession $chatSession): ?array
    {
        $knownCustomer = $chatSession instanceof ChatSession ? $chatSession->web_user_id : $chatSession->customer_id;

        if ($knownCustomer || (!$chatSession->suggested_customer_id && !$chatSession->suggestion_hint)) {
            return null;
        }

        // ponytail: one query per suggested row in a list; eager load if lists ever carry dozens
        $customer = $chatSession->suggested_customer_id && !$chatSession->suggestion_rejected_at
            ? Customer::find($chatSession->suggested_customer_id, ['id', 'name', 'email', 'reference'])
            : null;

        if (!$customer && !$chatSession->suggestion_hint) {
            return null;
        }

        return [
            'label' => match ($chatSession->suggestion_basis) {
                self::BASIS_EMAIL => __('Gave this customer\'s email'),
                self::BASIS_PHONE => __('Writes from this customer\'s number'),
                self::BASIS_ORDER => __('Quoted one of this customer\'s orders'),
                self::BASIS_PREVIOUS_LINK => __('Linked to this customer by hand before'),
                default           => '',
            },
            'basis'    => $customer ? $chatSession->suggestion_basis : null,
            'customer' => $customer?->only(['name', 'email', 'reference']),
            'hint'     => $customer ? null : $chatSession->suggestion_hint,
        ];
    }

    /**
     * @return array{0: ?Customer, 1: ?string}
     */
    private function inTheSameShop(ChatSession|MetaChatSession $chatSession, string $email, string $phone): array
    {
        $customers = fn () => Customer::where('shop_id', $chatSession->shop_id);

        if ($email !== '' && !($chatSession instanceof ChatSession && $chatSession->channel === ChatChannelEnum::EMAIL)) {
            $customer = $customers()->where('email', $email)->first();
            if ($customer) {
                return [$customer, self::BASIS_EMAIL];
            }
        }

        $references = $this->possibleOrderReferences($chatSession);
        if ($references) {
            $order = Order::where('shop_id', $chatSession->shop_id)->whereIn('reference', $references)->whereNotNull('customer_id')->first();
            if ($order) {
                return [$customers()->find($order->customer_id), self::BASIS_ORDER];
            }
        }

        // ponytail: customers.phone has no index, so this scans the shop's customers. Fine for a
        // queued job at a few guests a day; index the normalised phone if that changes.
        if (strlen($phone) >= 10) {
            $customer = $customers()->whereRaw("right(regexp_replace(phone, '\\D', '', 'g'), 9) = ?", [substr($phone, -9)])->first();
            if ($customer) {
                return [$customer, self::BASIS_PHONE];
            }
        }

        // A customer an agent picked by hand for this address earlier. Never a link on its own:
        // somebody else may have registered the address since, in which case the checks above
        // already found them and this is never reached.
        if ($email !== '' && $chatSession instanceof ChatSession) {
            $previous = ChatSession::where('shop_id', $chatSession->shop_id)
                ->where('id', '!=', $chatSession->id)
                ->where('suggestion_basis', self::BASIS_MANUAL)
                ->whereNotNull('web_user_id')
                ->whereRaw("lower(metadata->>'email') = ?", [$email])
                ->latest('id')
                ->with('webUser:id,customer_id')
                ->first();

            if ($previous?->webUser) {
                return [$customers()->find($previous->webUser->customer_id), self::BASIS_PREVIOUS_LINK];
            }
        }

        return [null, null];
    }

    private function hint(ChatSession|MetaChatSession $chatSession, string $email, string $phone): ?string
    {
        $elsewhere = fn () => Customer::where('group_id', $chatSession->shop->group_id)->where('shop_id', '!=', $chatSession->shop_id)->with('shop:id,name');

        $customer = $email !== '' ? $elsewhere()->where('email', $email)->first() : null;
        $customer ??= strlen($phone) >= 10
            ? $elsewhere()->where('phone', '+'.$phone)->first()
            : null;

        if ($customer) {
            return __('Customer of :shop as :name', ['shop' => $customer->shop?->name, 'name' => $customer->name]);
        }

        $domain = (string) substr(strrchr($email, '@') ?: '', 1);

        if ($domain === '' || in_array($domain, self::FREE_MAIL_DOMAINS, true)) {
            return null;
        }

        $colleague = Customer::where('shop_id', $chatSession->shop_id)
            ->whereRaw('lower(split_part(email collate "C", \'@\', 2)) = ?', [$domain])
            ->first();

        return $colleague ? __('Same company as customer :name', ['name' => $colleague->name]) : null;
    }

    /**
     * Order references come in too many shapes to describe (GB589201, AWD193607, HR02556), so
     * anything in what the guest wrote that could be one is simply looked up.
     *
     * @return array<int, string>
     */
    private function possibleOrderReferences(ChatSession|MetaChatSession $chatSession): array
    {
        $text = $chatSession->messages()
            ->where('sender_type', ChatSenderTypeEnum::GUEST)
            ->latest('id')
            ->limit(10)
            ->pluck('message_text')
            ->push((string) data_get($chatSession->metadata, 'email_subject'))
            ->join(' ');

        preg_match_all('/\b(?=[A-Za-z0-9-]*\d{4})[A-Za-z]{0,5}-?\d[A-Za-z0-9]{3,11}\b/', $text, $matches);

        return collect($matches[0])->map(fn (string $token) => strtoupper($token))->unique()->take(20)->values()->all();
    }
}
