<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Oct 2026 23:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Accounting\Invoice\RefundClaimToBalance;
use App\Actions\Dispatching\DeliveryNote\StoreReplacementDeliveryNote;
use App\Actions\Ordering\UpcomingTransaction\StoreClaimFollowOns;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Chat\ChatSession;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A claim on a chat's order, worked out the way the Claim panel does: which delivered items are
 * claimed, how much is still left to claim of each, and what a refund to the balance comes to.
 * The assistant's claim tool and the action buttons on a draft both plan and settle through here,
 * so a button can never refund more than the tool would. The amount is worked out as
 * RefundClaimToBalance pays it: the share of each invoiced line, capped at what is left of it.
 */
class PlanChatClaim
{
    use AsAction;

    public const array RESOLUTIONS = ['refund_to_balance', 'replacement', 'next_order'];

    /**
     * @param  array<int, array{code: string, quantity: float|int|string}>  $items
     * @return array{error: string|null, claimed: array<int, array<string, mixed>>, amount: float, currency: string|null, reason: string|null, percentage: float}
     */
    public function handle(ChatSession $chatSession, Order $order, string $resolution, array $items, ?string $reason = null, ?float $percentage = null): array
    {
        $refused = fn (string $error) => ['error' => $error, 'claimed' => [], 'amount' => 0.0, 'currency' => null, 'reason' => null, 'percentage' => 100.0];
        $claim   = GetChatClaimCase::run($chatSession, $chatSession->webUser?->customer, $order->reference);

        if (!$claim || $claim['order']['reference'] !== $order->reference || !$claim['products']) {
            return $refused("{$order->reference} has nothing delivered to claim on. Nothing was changed.");
        }

        if ($claim['order']['too_old']) {
            return $refused("Claims are taken for {$claim['max_age_days']} days after the order is dispatched, and {$order->reference} is older. Nothing was changed.");
        }

        $reason     = $reason ?: $claim['reason'];
        $percentage = $resolution === 'refund_to_balance' ? (float) ($percentage ?: 100) : 100.0;
        $lines      = collect($claim['products'])->keyBy(fn (array $line) => strtolower((string) $line['code']));
        $claimed    = [];
        $amount     = 0.0;

        $grouped = collect($items)
            ->groupBy(fn (array $item) => strtolower(trim($item['code'])))
            ->map(fn ($sameCode) => ['code' => trim($sameCode->first()['code']), 'quantity' => (float) $sameCode->sum('quantity')])
            ->values();

        foreach ($grouped as $item) {
            $line = $lines->get(strtolower($item['code']));

            if (!$line) {
                return $refused("{$item['code']} was not delivered on {$order->reference}. Delivered: ".$lines->pluck('code')->implode(', ').'. Nothing was changed.');
            }

            if ($item['quantity'] > $line['claimable'] + 0.0005) {
                return $refused("Only {$line['claimable']} of {$line['code']} is left to claim on {$order->reference}: the rest is already replaced, refunded or waiting in their next order. Nothing was changed.");
            }

            $share = min(1, $item['quantity'] / $line['dispatched']);

            $claimed[] = [
                'code'     => $line['code'],
                'name'     => $line['name'],
                'quantity' => $item['quantity'],
                'items'    => array_map(fn (array $stockLine) => ['id' => $stockLine['id'], 'quantity' => round($stockLine['quantity'] * $share, 3), 'reason' => $reason], $line['items']),
            ];

            $amount += min($claim['invoiced'][$line['transaction_id']] ?? 0, round(($claim['invoiced_net'][$line['transaction_id']] ?? 0) * $share * $percentage / 100, 2)) * $claim['tax_ratio'];
        }

        return ['error' => null, 'claimed' => $claimed, 'amount' => round($amount, 2), 'currency' => $claim['currency'], 'reason' => $reason, 'percentage' => $percentage];
    }

    public static function permission(string $resolution): ShopPermissionsEnum
    {
        return $resolution === 'replacement' ? ShopPermissionsEnum::ORDERS_EDIT : ShopPermissionsEnum::CRM_EDIT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $claimed  from handle()
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    public function settle(Order $order, string $resolution, array $claimed, string $reason, float $percentage, ?string $note = null): array
    {
        $items = collect($claimed)->flatMap(fn (array $product) => $product['items'])->all();

        if ($resolution === 'refund_to_balance') {
            $refund = RefundClaimToBalance::make()->handle($order, $items, $percentage);

            return ['refund' => $refund->reference, 'credited' => abs((float) $refund->total_amount)];
        }

        if ($resolution === 'next_order') {
            return [
                'in_next_order' => StoreClaimFollowOns::make()->handle($order, $items, $reason, $note)
                    ->map(fn ($followOn) => $followOn->product?->code.' x'.(float) $followOn->quantity)
                    ->all(),
            ];
        }

        $deliveryNote = StoreReplacementDeliveryNote::make()->action($order, array_filter([
            'delivery_note_items'    => $items,
            'private_warehouse_note' => $note,
        ]));

        return ['replacement' => $deliveryNote->reference, 'state' => $deliveryNote->state->value];
    }
}
