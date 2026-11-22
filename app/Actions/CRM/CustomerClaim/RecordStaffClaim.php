<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Actions\Chat\ChatSession\GetChatClaimCase;
use App\Enums\CRM\CustomerClaim\CustomerClaimKindEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimSourceEnum;
use App\Enums\CRM\CustomerClaim\CustomerClaimStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Support\Carbon;
use App\Models\Ordering\UpcomingTransaction;
use App\Models\Ordering\Transaction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Every refund, tax refund, replacement and free next-order item staff give a customer is recorded
 * as a claim settled by them (HELP-4869), so nothing goes back to a customer outside the claims:
 * the customer's claim number, the Claims pages and the dashboard count everything. It only adds
 * the record next to what the staff action already did, and a failure to record is logged, never
 * thrown, so it cannot undo or block the refund or replacement itself.
 */
class RecordStaffClaim
{
    use AsAction;

    /**
     * The value of delivered items, worked out before they are settled (afterwards nothing is left
     * to claim): each order line's invoiced amount with tax, in the share of it claimed.
     *
     * @param  array<int, array{id: int|string, quantity: float|int|string, reason?: string|null}>  $items  delivery note items
     */
    public static function itemsValue(Order $order, array $items): float
    {
        try {
            $case   = GetChatClaimCase::make()->orderCase($order);
            $shares = [];
            foreach ($case['products'] as $product) {
                foreach ($product['items'] as $stockLine) {
                    $shares[(int) $stockLine['id']] = [$product['transaction_id'], $stockLine['quantity']];
                }
            }

            $value = 0.0;
            foreach ($items as $item) {
                [$transactionId, $sent] = $shares[(int) $item['id']] ?? [null, 0];
                if ($transactionId && $sent > 0) {
                    $value += ($case['invoiced_net'][$transactionId] ?? 0) * min(1, (float) $item['quantity'] / $sent) * $case['tax_ratio'];
                }
            }

            return round($value, 2);
        } catch (Throwable) {
            return 0.0;
        }
    }

    /**
     * @param  array<int, array{id: int|string, quantity: float|int|string, reason?: string|null}>  $items  delivery note items
     * @param  array<string, mixed>  $result
     */
    public function fromItems(Order $order, string $resolution, array $items, array $result, ?User $user, float $value, ?string $reason = null): ?CustomerClaim
    {
        return $this->safely(function () use ($order, $resolution, $items, $result, $user, $value, $reason) {
            $deliveryNoteItems = DeliveryNoteItem::whereIn('id', array_column($items, 'id'))->with('transaction.asset')->get()->keyBy('id');
            $lines             = collect($items)
                ->groupBy(fn (array $item) => $deliveryNoteItems->get((int) $item['id'])?->transaction_id)
                ->filter(fn ($group, $transactionId) => $transactionId)
                ->map(function ($group, $transactionId) use ($deliveryNoteItems, $reason) {
                    $first = $deliveryNoteItems->get((int) $group->first()['id']);

                    return [
                        'transaction_id'      => (int) $transactionId,
                        'code'                => (string) ($first->transaction?->asset?->code ?? $first->org_stock_id),
                        'name'                => $first->transaction?->asset?->name,
                        'quantity'            => (float) $group->max(fn (array $item) => (float) $item['quantity']),
                        'reason'              => DeliveryNoteItemReplacementReasonEnum::tryFrom((string) ($group->first()['reason'] ?? $reason))?->value ?? DeliveryNoteItemReplacementReasonEnum::OTHER->value,
                        'delivery_note_items' => $group->map(fn (array $item) => ['id' => (int) $item['id'], 'quantity' => (float) $item['quantity'], 'reason' => $item['reason'] ?? $reason])->values()->all(),
                    ];
                })
                ->values()
                ->all();

            return $this->store($order->customer, $order, CustomerClaimKindEnum::ITEMS, $resolution, $lines, $result, $user, $value, $resolution === 'refund_to_balance' ? abs((float) ($result['credited'] ?? $value)) : 0.0, null);
        });
    }

    public function fromRefund(Invoice $refund, ?User $user): ?CustomerClaim
    {
        return $this->safely(function () use ($refund, $user) {
            $opened = CustomerClaim::where('refund_id', $refund->id)->where('state', CustomerClaimStateEnum::IN_REVIEW)->first();
            if ($opened) {
                $amount = abs((float) $refund->total_amount);
                $opened->update([
                    'state'               => CustomerClaimStateEnum::SETTLED,
                    'amount'              => $amount,
                    'value'               => $amount,
                    'result'              => array_merge($opened->result ?? [], ['refund' => $refund->reference, 'credited' => $amount]),
                    'reviewed_by_user_id' => $user?->id ?? $opened->reviewed_by_user_id,
                    'reviewed_at'         => now(),
                    'settled_at'          => now(),
                ]);

                return $opened;
            }

            if (CustomerClaim::where('refund_id', $refund->id)->exists() || CustomerClaim::where('result->refund', $refund->reference)->where('shop_id', $refund->shop_id)->exists()) {
                return null;
            }

            $kind  = $refund->is_tax_only ? CustomerClaimKindEnum::TAX_CORRECTION : CustomerClaimKindEnum::REFUND;
            $lines = $refund->invoiceTransactions()->whereNotNull('transaction_id')->with('historicAsset')->get()
                ->groupBy('transaction_id')
                ->map(fn ($group, $transactionId) => [
                    'transaction_id'      => (int) $transactionId,
                    'code'                => (string) ($group->first()->historicAsset?->code ?? ''),
                    'name'                => $group->first()->historicAsset?->name,
                    'quantity'            => abs((float) $group->sum('quantity')),
                    'reason'              => DeliveryNoteItemReplacementReasonEnum::OTHER->value,
                    'delivery_note_items' => [],
                ])
                ->values()
                ->all();
            $amount = abs((float) $refund->total_amount);

            return $this->store($refund->customer, $refund->order, $kind, 'refund_to_balance', $lines, ['refund' => $refund->reference, 'credited' => $amount], $user, $amount, $amount, $refund->id);
        });
    }

    /**
     * A refund from before claims existed, as a settled claim on the day it was made. Already
     * recorded refunds are skipped, so it is safe to run again.
     */
    public function backfillRefund(Invoice $refund): ?CustomerClaim
    {
        if (CustomerClaim::where('refund_id', $refund->id)->exists() || CustomerClaim::where('shop_id', $refund->shop_id)->where('result->refund', $refund->reference)->exists() || !$refund->customer) {
            return null;
        }

        $amount = abs((float) $refund->total_amount);
        $lines  = $refund->invoiceTransactions()->whereNotNull('transaction_id')->with('historicAsset')->get()
            ->groupBy('transaction_id')
            ->map(fn ($group, $transactionId) => [
                'transaction_id'      => (int) $transactionId,
                'code'                => (string) ($group->first()->historicAsset?->code ?? ''),
                'name'                => $group->first()->historicAsset?->name,
                'quantity'            => abs((float) $group->sum('quantity')),
                'reason'              => DeliveryNoteItemReplacementReasonEnum::OTHER->value,
                'delivery_note_items' => [],
            ])->values()->all();

        return $this->store($refund->customer, $refund->order, $refund->is_tax_only ? CustomerClaimKindEnum::TAX_CORRECTION : CustomerClaimKindEnum::REFUND, 'refund_to_balance', $lines, ['refund' => $refund->reference, 'credited' => $amount, 'backfilled' => true], null, $amount, $amount, $refund->id, $refund->date ?? $refund->created_at);
    }

    /**
     * A replacement delivery note from before claims existed. Its value is each line's invoiced net
     * per unit ordered, with tax, times what was replaced.
     */
    public function backfillReplacement(DeliveryNote $replacement): ?CustomerClaim
    {
        if (CustomerClaim::where('shop_id', $replacement->shop_id)->where('result->replacement', $replacement->reference)->exists() || !$replacement->customer) {
            return null;
        }

        $value = 0.0;
        $lines = $replacement->deliveryNoteItems()->with('transaction.asset')->get()
            ->filter(fn (DeliveryNoteItem $item) => $item->transaction_id)
            ->groupBy('transaction_id')
            ->map(function ($items, $transactionId) use (&$value) {
                $transaction = $items->first()->transaction;
                $ordered     = (float) ($transaction?->quantity_ordered ?? 0);
                $quantity    = (float) $items->max('quantity_required');
                if ($transaction && $ordered > 0) {
                    $value += (float) $transaction->net_amount / $ordered * min($quantity, $ordered);
                }

                return [
                    'transaction_id'      => (int) $transactionId,
                    'code'                => (string) ($transaction?->asset?->code ?? $items->first()->org_stock_id),
                    'name'                => $transaction?->asset?->name,
                    'quantity'            => $quantity,
                    'reason'              => $items->first()->replacement_reason?->value ?? DeliveryNoteItemReplacementReasonEnum::OTHER->value,
                    'delivery_note_items' => $items->map(fn (DeliveryNoteItem $item) => ['id' => $item->id, 'quantity' => (float) $item->quantity_required])->values()->all(),
                ];
            })->values()->all();

        return $this->store($replacement->customer, $replacement->order, CustomerClaimKindEnum::ITEMS, 'replacement', $lines, ['replacement' => $replacement->reference, 'state' => $replacement->state->value, 'backfilled' => true], null, round($value, 2), 0.0, null, $replacement->created_at);
    }

    /**
     * Items given free in a next order from before claims were recorded: the follow-ons made
     * together for one order, as one claim.
     *
     * @param  \Illuminate\Support\Collection<int, UpcomingTransaction>  $followOns
     */
    public function backfillFollowOns($followOns): ?CustomerClaim
    {
        $first    = $followOns->first();
        $customer = $first?->customer;
        if (!$customer || CustomerClaim::where('customer_id', $customer->id)->where('resolution', 'next_order')->whereBetween('created_at', [$first->created_at->copy()->subMinutes(2), $first->created_at->copy()->addMinutes(2)])->exists()) {
            return null;
        }

        $sources = Transaction::whereIn('id', $followOns->pluck('source_transaction_id')->filter())->with('asset')->get()->keyBy('id');
        $order   = $sources->first()?->order;
        $value   = 0.0;
        $lines   = $followOns->map(function (UpcomingTransaction $followOn) use ($sources, &$value) {
            $source  = $sources->get($followOn->source_transaction_id);
            $ordered = (float) ($source?->quantity_ordered ?? 0);
            if ($source && $ordered > 0) {
                $value += (float) $source->net_amount / $ordered * (float) $followOn->quantity;
            }

            return [
                'transaction_id'      => (int) ($followOn->source_transaction_id ?? 0),
                'code'                => (string) ($followOn->product?->code ?? ''),
                'name'                => $followOn->product?->name,
                'quantity'            => (float) $followOn->quantity,
                'reason'              => DeliveryNoteItemReplacementReasonEnum::OTHER->value,
                'delivery_note_items' => [],
            ];
        })->values()->all();

        return $this->store($customer, $order, CustomerClaimKindEnum::ITEMS, 'next_order', $lines, ['in_next_order' => $followOns->map(fn ($followOn) => $followOn->product?->code.' x'.(float) $followOn->quantity)->all(), 'backfilled' => true], null, round($value, 2), 0.0, null, $first->created_at);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $result
     */
    private function store(Customer $customer, ?Order $order, CustomerClaimKindEnum $kind, string $resolution, array $lines, array $result, ?User $user, float $value, float $amount, ?int $refundId, ?Carbon $at = null): CustomerClaim
    {
        $lock = Cache::lock('customer-claim-customer-'.$customer->id, 30);
        $lock->block(10);

        try {
            $claim = CustomerClaim::create([
                'ulid'                => (string) Str::ulid(),
                'group_id'            => $customer->group_id,
                'organisation_id'     => $customer->organisation_id,
                'shop_id'             => $customer->shop_id,
                'customer_id'         => $customer->id,
                'order_id'            => $order?->id,
                'refund_id'           => $refundId,
                'reference'           => StoreCustomerClaim::reference($customer),
                'kind'                => $kind,
                'source'              => CustomerClaimSourceEnum::STAFF,
                'state'               => CustomerClaimStateEnum::SETTLED,
                'resolution'          => $resolution,
                'amount'              => round($amount, 2),
                'value'               => round($value, 2),
                'currency_code'       => $customer->shop->currency?->code,
                'result'              => $result,
                'reviewed_by_user_id' => $user?->id,
                'reviewed_at'         => $at ?? now(),
                'settled_at'          => $at ?? now(),
                'created_at'          => $at ?? now(),
                'updated_at'          => $at ?? now(),
            ]);
            foreach ($lines as $line) {
                $claim->lines()->create($line);
            }

            return $claim;
        } finally {
            $lock->release();
        }
    }

    private function safely(callable $record): ?CustomerClaim
    {
        try {
            return $record();
        } catch (Throwable $exception) {
            Log::warning('Staff claim not recorded: '.$exception->getMessage());
            report($exception);

            return null;
        }
    }
}
