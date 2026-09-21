<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\Accounting\OrderPaymentApiPoint\StoreOrderPaymentLink;
use App\Actions\Ordering\Order\SaveOrderModification;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Actions\Ordering\Order\StoreFollowUpOrder;
use App\Actions\Helpers\Address\GetFormattedAddress;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use App\Models\Ordering\Order;

class GetChatCustomerProfile
{
    use AsAction;

    public function handle(ChatSession $chatSession): array
    {
        $webUser = $chatSession->webUser()
            ->with(['customer.tags', 'customer.stats', 'customer.shop.currency', 'customer.organisation'])
            ->first();

        if (!$webUser || !$webUser->customer) {
            return ['tags' => [], 'stats' => null, 'email' => null, 'profile_url' => null];
        }

        $customer = $webUser->customer;
        $stats    = $customer->stats;
        $currency = $customer->shop?->currency;

        return [
            'email'       => $customer->email ?: $webUser->email,
            'profile_url' => $this->customerProfileUrl($customer),
            ...$this->contactAndLastOrders($customer),
            ...$this->previousContact($customer, $chatSession),

            'tags'  => $customer->tags->map(fn ($tag) => [
                'id'   => $tag->id,
                'name' => $tag->label['en'] ?? $tag->name,
                'slug' => $tag->slug,
            ])->values()->all(),

            'stats' => $stats ? [
                'currency_symbol'        => $currency?->symbol ?? '',
                'number_orders'          => $stats->number_orders,
                'sales_all'              => $stats->sales_all,
                'average_order_value'    => $stats->average_order_value,
                'last_invoiced_at'  => $stats->last_invoiced_at,
                'first_order_date'       => $stats->first_order_date,
                'number_invoices'        => $stats->number_invoices,
                'number_returns'         => $stats->number_returns,
                'number_orders_state_creating' => $stats->number_orders_state_creating,
            ] : null,
        ];
    }

    /**
     * @return array{company_name: ?string, phone: ?string, location: ?array{0: ?string, 1: ?string, 2: ?string}, address: ?string, last_orders: array<int, array{reference: string, date: ?string, state: string, total: string, url: ?string, add_items: ?array{products: array, save: array}, follow_up: ?array, payment_link: ?array}>}
     */
    public function contactAndLastOrders(Customer $customer): array
    {
        $stateLabels  = OrderStateEnum::labels();
        $organisation = $customer->organisation;
        $shop         = $customer->shop;
        $canEditOrders = $shop && (bool)request()->user()?->authTo(["orders.{$shop->id}.edit"]);
        $takesPaymentLinks = $canEditOrders && $shop->paymentAccountShops()
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->where('type', PaymentAccountTypeEnum::CHECKOUT)
            ->exists();
        $location     = is_string($customer->location) ? json_decode($customer->location, true) : $customer->location;

        return [
            'company_name' => $customer->company_name,
            'phone'        => $customer->phone,
            'location'     => is_array($location) && Arr::get($location, 0) ? array_values($location) : null,
            'address'      => $customer->address ? GetFormattedAddress::run($customer->address) : null,
            'last_orders'  => $customer->orders()
                ->where('state', '!=', OrderStateEnum::CREATING)
                ->latest('date')
                ->limit(5)
                ->with('platform')
                ->get()
                ->each->setRelation('shop', $shop)
                ->map(fn (Order $order) => [
                    'reference' => $order->reference,
                    'date'      => $order->date?->toIso8601String(),
                    'state'     => $stateLabels[$order->state->value] ?? $order->state->value,
                    'total'     => $order->total_amount,
                    'url'       => $organisation && $shop
                        ? route('grp.org.shops.show.crm.customers.show.orders.show', [$organisation->slug, $shop->slug, $customer->slug, $order->slug])
                        : null,
                    'add_items' => $canEditOrders && SaveOrderModification::acceptsNewProducts($order)
                        ? [
                            'products' => ['name' => 'grp.json.order.products_for_modify', 'parameters' => ['order' => $order->id]],
                            'save'     => ['name' => 'grp.models.order.modification.save', 'parameters' => ['order' => $order->id]],
                        ]
                        : null,
                    'follow_up' => $canEditOrders && StoreFollowUpOrder::offersFollowUp($order)
                        ? ['name' => 'grp.models.order.follow_up.store', 'parameters' => ['order' => $order->id]]
                        : null,
                    'payment_link' => $takesPaymentLinks && $order->state != OrderStateEnum::CANCELLED && StoreOrderPaymentLink::amountDue($order) > 0
                        ? ['name' => 'grp.models.order.payment_link.store', 'parameters' => ['order' => $order->id]]
                        : null,
                ])->all(),
        ];
    }

    /**
     * What this customer wrote to us about over the last year, on every channel, leaving out
     * the conversation being looked at: the last few in a line each, and how often each topic
     * came up. Counted from the topic given to each conversation, so it says what happened
     * and leaves the judging to whoever reads it.
     *
     * @return array{previous_chats: array<int, array{ulid: string, channel: string, date: ?string, topic: ?string, summary: ?string, status: ?string}>, chat_topics: array<int, array{topic: string, label: string, count: int}>}
     */
    public function previousContact(Customer $customer, ChatSession|MetaChatSession $current): array
    {
        $columns = ['id', 'ulid', 'topic', 'metadata', 'created_at'];

        $chatSessions = ChatSession::query()
            ->whereIn('web_user_id', $customer->webUsers()->select('id'))
            ->where('is_rubbish', false)
            ->where('is_spam', false)
            ->whereNotNull('topic')
            ->where('created_at', '>=', now()->subYear())
            ->get([...$columns, 'channel'])
            ->concat(
                MetaChatSession::query()
                    ->where('customer_id', $customer->id)
                    ->where('is_spam', false)
                    ->whereNotNull('topic')
                    ->where('created_at', '>=', now()->subYear())
                    ->get($columns)
            )
            ->reject(fn (ChatSession|MetaChatSession $chatSession) => $chatSession->is($current))
            ->sortByDesc('created_at')
            ->values();

        $topicLabels = ChatTopicEnum::labels();

        return [
            'previous_chats' => $chatSessions->take(3)->map(fn (ChatSession|MetaChatSession $chatSession) => [
                'ulid'    => $chatSession->ulid,
                'channel' => $chatSession instanceof MetaChatSession ? 'whatsapp' : ($chatSession->channel?->value ?? 'website'),
                'date'    => $chatSession->created_at?->toIso8601String(),
                'topic'   => $topicLabels[$chatSession->topic] ?? null,
                'summary' => Arr::get($chatSession->metadata ?? [], 'ai_summary.summary'),
                'status'  => Arr::get($chatSession->metadata ?? [], 'ai_summary.status'),
            ])->all(),
            'chat_topics' => $chatSessions
                ->where('topic', '!=', ChatTopicEnum::NO_REQUEST->value)
                ->countBy('topic')
                ->sortDesc()
                ->map(fn (int $count, string $topic) => ['topic' => $topic, 'label' => $topicLabels[$topic] ?? $topic, 'count' => $count])
                ->values()
                ->all(),
        ];
    }

    private function customerProfileUrl(Customer $customer): ?string
    {
        $organisation = $customer->organisation;
        $shop         = $customer->shop;

        if (!$organisation || !$shop) {
            return null;
        }

        return route('grp.org.shops.show.crm.customers.show', [
            $organisation->slug,
            $shop->slug,
            $customer->slug,
        ]);
    }

    public function asController(ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        return response()->json($this->handle($chatSession));
    }
}
