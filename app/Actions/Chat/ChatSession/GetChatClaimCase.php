<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionStateEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionTypeEnum;
use App\Actions\Ordering\Order\CheckClaimCompensation;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Ordering\Order;
use App\Models\Ordering\UpcomingTransaction;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What an agent otherwise looks up by hand before answering a claim: which order it is, what
 * was ordered against what left the warehouse, and which of those lines the customer names.
 * Watching agents work claims, half of them started by searching the orders list and most
 * then opened the order's lines and its delivery note; most claims end in a replacement,
 * which is made from the lines given here.
 */
class GetChatClaimCase
{
    use AsAction;

    /**
     * @return array<string, mixed>|null
     */
    public function handle(ChatSession|MetaChatSession $chatSession, Customer $customer, ?string $pickedOrderReference = null): ?array
    {
        $details = GetChatClaimDetails::run($chatSession);
        $order   = ($pickedOrderReference ? $customer->orders()->where('reference', $pickedOrderReference)->first() : null)
            ?? $this->order($customer, $details['order_reference']);

        if (!$order) {
            return null;
        }

        $text  = mb_strtolower($details['text']);
        $left  = CheckClaimCompensation::run($order);
        $items = DeliveryNoteItem::query()
            ->whereIn('delivery_note_id', $order->deliveryNotes()->where('type', DeliveryNoteTypeEnum::ORDER)->where('state', '!=', DeliveryNoteStateEnum::CANCELLED->value)->pluck('delivery_notes.id'))
            ->with(['transaction.asset', 'orgStock'])
            ->orderBy('id')
            ->get();

        $lines = $items
            ->map(function (DeliveryNoteItem $item) use ($text, $left) {
                $code = $item->transaction?->asset?->code ?? $item->orgStock?->code;

                return [
                    'id'         => $item->id,
                    'transaction_id' => $item->transaction_id,
                    'org_stock_id' => $item->org_stock_id,
                    'code'       => $code,
                    'name'       => $item->transaction?->asset?->name ?? $item->orgStock?->name,
                    'ordered'    => (float) $item->quantity_required,
                    'dispatched' => (float) ($item->quantity_dispatched ?? 0),
                    'claimable'  => floor(($left[$item->transaction_id] ?? 1) * (float) $item->quantity_required * 1000 + 0.001) / 1000,
                    'mentioned'  => $code !== null && str_contains($text, mb_strtolower($code)),
                ];
            })
            ->values()
            ->all();

        return [
            'is_claim'        => $this->looksLikeAClaim($chatSession),
            'order'           => [
                'id'        => $order->id,
                'reference' => $order->reference,
                'state'     => $order->state->value,
                'named'     => $details['order_reference'] === $order->reference || $pickedOrderReference === $order->reference,
                'too_old'   => CheckClaimCompensation::isTooOld($order),
            ],
            'max_age_days'    => CheckClaimCompensation::MAX_AGE_DAYS,
            'orders'          => $customer->orders()
                ->whereIn('state', [OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED])
                ->where('dispatched_at', '>=', now()->subDays(CheckClaimCompensation::MAX_AGE_DAYS))
                ->latest('dispatched_at')
                ->limit(10)
                ->pluck('reference')
                ->push($order->reference)
                ->unique()
                ->values()
                ->all(),
            'photos'          => $details['photos'],
            'lines'           => $lines,
            'products'        => $this->products($items, $text, $left),
            'reason'          => $this->reason($text)->value,
            'reasons'         => collect(DeliveryNoteItemReplacementReasonEnum::cases())
                ->map(fn (DeliveryNoteItemReplacementReasonEnum $reason) => ['value' => $reason->value, 'label' => $reason->label()])
                ->all(),
            'replacement'     => ['name' => 'grp.models.order.replacement_delivery_note.store', 'parameters' => ['order' => $order->id]],
            'replacements'    => $order->deliveryNotes()->where('type', DeliveryNoteTypeEnum::REPLACEMENT)->pluck('reference')->all(),
            'follow_on'       => !$order->customer_client_id && request()->user()?->authTo("crm.{$order->shop_id}.edit")
                ? ['name' => 'grp.models.order.claim_follow_on', 'parameters' => ['order' => $order->id]]
                : null,
            'follow_ons'      => $customer->upcomingTransactions()
                ->where('type', UpcomingTransactionTypeEnum::FOLLOW_ON)
                ->where('state', UpcomingTransactionStateEnum::READY)
                ->with('product:id,code')
                ->get()
                ->map(fn (UpcomingTransaction $followOn) => $followOn->product?->code.' ×'.(float) $followOn->quantity)
                ->all(),
            ...$this->refund($order),
        ];
    }

    /**
     * What refunding to balance needs: the invoice the lines are refunded from, what each
     * claimable line was invoiced at, and the refunds already made. The button is offered only
     * to CRM editors of the shop.
     *
     * @return array<string, mixed>
     */
    private function refund(Order $order): array
    {
        $invoice = $order->invoices()->where('type', InvoiceTypeEnum::INVOICE)->where(fn ($query) => $query->where('in_process', false)->orWhereNull('in_process'))->latest('id')->first();

        return [
            'invoiced'  => $invoice ? $invoice->invoiceTransactions()->whereNotNull('transaction_id')->with('transactionRefunds')->get()
                ->mapWithKeys(fn ($line) => [$line->transaction_id => max(0, round((float) $line->net_amount - abs((float) $line->transactionRefunds->where('in_process', false)->sum('net_amount')), 2))])
                ->all() : [],
            'invoiced_net' => $invoice ? $invoice->invoiceTransactions()->whereNotNull('transaction_id')->pluck('net_amount', 'transaction_id')->map(fn ($net) => (float) $net)->all() : [],
            'tax_ratio' => $invoice && (float) $invoice->net_amount != 0.0 ? round((float) $invoice->total_amount / (float) $invoice->net_amount, 4) : 1,
            'currency'  => $order->currency?->code,
            'refunds'   => $order->invoices()->where('type', InvoiceTypeEnum::REFUND)->pluck('reference')->all(),
            'refund'    => $invoice && request()->user()?->authTo("crm.{$order->shop_id}.edit")
                ? ['name' => 'grp.models.order.claim_refund_to_balance', 'parameters' => ['order' => $order->id]]
                : null,
        ];
    }

    /**
     * The lines above are what the warehouse picked, one per stock and counted in stocks: a
     * 3-pack ordered twice is 6, a set of four stocks is four lines. Customers and the assistant
     * talk in products as ordered, so these are the same lines per order line, in its units,
     * with the stock lines a claim on it is spread over.
     *
     * @param  \Illuminate\Support\Collection<int, DeliveryNoteItem>  $items
     * @param  array<int, float>  $left
     *
     * @return array<int, array{transaction_id: int, code: ?string, name: ?string, ordered: float, claimable: float, mentioned: bool, items: array<int, array{id: int, quantity: float}>}>
     */
    private function products($items, string $text, array $left): array
    {
        return $items->filter(fn (DeliveryNoteItem $item) => $item->transaction)
            ->groupBy('transaction_id')
            ->map(function ($stockLines, $transactionId) use ($text, $left) {
                $transaction = $stockLines->first()->transaction;
                $code        = $transaction->asset?->code;
                $ordered     = (float) $transaction->quantity_ordered + (float) $transaction->quantity_bonus;

                return [
                    'transaction_id' => (int) $transactionId,
                    'code'           => $code,
                    'name'           => $transaction->asset?->name,
                    'ordered'        => $ordered,
                    'claimable'      => floor(($left[$transactionId] ?? 1) * $ordered * 1000 + 0.001) / 1000,
                    'mentioned'      => $code !== null && str_contains($text, mb_strtolower($code)),
                    'items'          => $stockLines->map(fn (DeliveryNoteItem $item) => ['id' => $item->id, 'quantity' => (float) $item->quantity_required])->values()->all(),
                ];
            })
            ->filter(fn (array $product) => $product['ordered'] > 0)
            ->values()
            ->all();
    }

    private function order(Customer $customer, ?string $reference): ?Order
    {
        return ($reference ? $customer->orders()->where('reference', $reference)->first() : null)
            ?? $customer->orders()
                ->whereIn('state', [OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED])
                ->latest('dispatched_at')
                ->first();
    }

    private function looksLikeAClaim(ChatSession|MetaChatSession $chatSession): bool
    {
        return $chatSession->topic === ChatTopicEnum::MISSING_OR_DAMAGED->value
            || data_get($chatSession->metadata, SendOutOfHoursReply::CLAIM_KEY) !== null;
    }

    private function reason(string $text): DeliveryNoteItemReplacementReasonEnum
    {
        return match (true) {
            (bool) preg_match('/\b(broken|damaged|smashed|cracked|leak\w*|beschädigt|kaputt|roto|rota|dañad\w*|cass[ée]|endommag\w*|rotto|poškoz\w*|rozbit\w*|zniszcz\w*)\b/iu', $text) => DeliveryNoteItemReplacementReasonEnum::DAMAGED_IN_TRANSIT,
            (bool) preg_match('/\b(wrong|instead of|falsch|equivocad\w*|incorrect\w*|sbagliat\w*|nesprávn\w*|zł\w*)\b/iu', $text)                                                         => DeliveryNoteItemReplacementReasonEnum::WRONG_ITEM_SENT,
            default                                                                                                                                                                      => DeliveryNoteItemReplacementReasonEnum::MISSING_FROM_PARCEL,
        };
    }
}
