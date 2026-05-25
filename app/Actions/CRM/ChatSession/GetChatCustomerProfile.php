<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: YYYY-MM-DD HH:mm:ss
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: YYYY-MM-DD HH:mm:ss
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\CRM\ChatSession;

use App\Models\CRM\Livechat\ChatSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatCustomerProfile
{
    use AsAction;

    public function handle(ChatSession $chatSession): array
    {
        $webUser = $chatSession->webUser()->with(['customer.tags', 'customer.stats', 'customer.shop.currency'])->first();

        if (!$webUser || !$webUser->customer) {
            return ['tags' => [], 'stats' => null];
        }

        $customer = $webUser->customer;
        $stats    = $customer->stats;
        $currency = $customer->shop?->currency;

        return [
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

    public function asController(ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        return response()->json($this->handle($chatSession));
    }
}
