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
use App\Models\CRM\Customer;

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
