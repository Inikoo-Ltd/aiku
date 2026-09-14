<?php

namespace App\Actions\Chat\MetaChatSession;

use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMetaChatCustomerProfile
{
    use AsAction;

    public function handle(MetaChatSession $metaChatSession): array
    {
        $customer = $metaChatSession->customer_id
            ? Customer::with(['tags', 'stats', 'shop.currency', 'organisation'])
                ->find($metaChatSession->customer_id)
            : null;

        if (!$customer) {
            return ['tags' => [], 'stats' => null, 'email' => null, 'phone' => null, 'profile_url' => null];
        }

        $stats    = $customer->stats;
        $currency = $customer->shop?->currency;

        return [
            'email'       => $customer->email,
            'phone'       => $customer->phone,
            'profile_url' => $this->customerProfileUrl($customer),

            'tags' => $customer->tags->map(fn ($tag) => [
                'id'   => $tag->id,
                'name' => $tag->label['en'] ?? $tag->name,
                'slug' => $tag->slug,
            ])->values()->all(),

            'stats' => $stats ? [
                'currency_symbol'              => $currency?->symbol ?? '',
                'number_orders'                => $stats->number_orders,
                'sales_all'                    => $stats->sales_all,
                'average_order_value'          => $stats->average_order_value,
                'last_invoiced_at'             => $stats->last_invoiced_at,
                'first_order_date'             => $stats->first_order_date,
                'number_invoices'              => $stats->number_invoices,
                'number_returns'               => $stats->number_returns,
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

    public function asController(MetaChatSession $metaChatSession, ActionRequest $request): JsonResponse
    {
        return response()->json($this->handle($metaChatSession));
    }
}
