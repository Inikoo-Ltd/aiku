<?php

/*
 * Author: Yudhistira <artha@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the inbox search box offers while somebody types: customers who have written to us, by name
 * or email address, and orders by reference, over the shops picked on the rail that the person may
 * look at chat on.
 */
class GetChatSearchSuggestions
{
    use AsAction;
    use WithChatAgentAuthorisation;

    private const int CUSTOMERS = 6;
    private const int ORDERS = 4;

    public function rules(): array
    {
        return [
            'q'          => ['required', 'string', 'min:2', 'max:100'],
            'shop_ids'   => ['required', 'array'],
            'shop_ids.*' => ['integer'],
        ];
    }

    /**
     * @param  array<int, int>  $shopIds
     * @return Collection<int, array{type: string, label: string, detail: string|null, value: string}>
     */
    public function handle(User $viewer, string $search, array $shopIds): Collection
    {
        $allowedShopIds = Shop::with('fulfilment')
            ->whereIn('id', $shopIds)
            ->get()
            ->filter(fn (Shop $shop) => $this->userCanViewChatOnShop($viewer, $shop))
            ->pluck('id')
            ->all();

        if ($allowedShopIds === []) {
            return collect();
        }

        $term = '%'.addcslashes(mb_strtolower(trim($search)), '%_\\').'%';

        $customers = Customer::query()
            ->whereIn('shop_id', $allowedShopIds)
            ->where(fn ($query) => $query->whereRaw('LOWER(name COLLATE "C") LIKE ?', [$term])
                ->orWhereRaw('LOWER(contact_name COLLATE "C") LIKE ?', [$term])
                ->orWhereRaw('LOWER(company_name COLLATE "C") LIKE ?', [$term])
                ->orWhereRaw('LOWER(email COLLATE "C") LIKE ?', [$term]))
            ->whereExists(fn ($conversations) => $conversations->selectRaw('1')
                ->from('chat_sessions')
                ->join('web_users', 'web_users.id', '=', 'chat_sessions.web_user_id')
                ->whereColumn('web_users.customer_id', 'customers.id'))
            ->orderBy('name')
            ->limit(self::CUSTOMERS)
            ->get(['id', 'name', 'contact_name', 'email'])
            ->map(fn (Customer $customer) => [
                'type'   => 'customer',
                'label'  => $customer->name ?: $customer->contact_name ?: (string) $customer->email,
                'detail' => $customer->email,
                'value'  => $customer->email ?: ($customer->name ?: (string) $customer->contact_name),
            ]);

        $reference = ltrim(trim($search), '#');

        $orders = mb_strlen($reference) >= 4 && preg_match('/\d/', $reference)
            ? Order::query()
                ->whereIn('shop_id', $allowedShopIds)
                ->whereRaw('reference COLLATE "C" LIKE ?', [mb_strtoupper(addcslashes($reference, '%_\\')).'%'])
                ->orderByDesc('id')
                ->limit(self::ORDERS)
                ->get(['id', 'reference', 'customer_id'])
                ->map(fn (Order $order) => [
                    'type'   => 'order',
                    'label'  => $order->reference,
                    'detail' => null,
                    'value'  => $order->reference,
                ])
            : collect();

        return $orders->concat($customers)->values();
    }

    public function asController(ActionRequest $request): Collection
    {
        $validated = $request->validated();

        return $this->handle($request->user(), $validated['q'], array_map('intval', $validated['shop_ids']));
    }

    public function jsonResponse(Collection $suggestions): JsonResponse
    {
        return response()->json(['data' => $suggestions]);
    }
}
