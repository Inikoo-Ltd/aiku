<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Ordering\Transaction\UpcomingTransactionTypeEnum;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\Ordering\UpcomingTransaction;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A damaged or missing line is made good once (HELP-3771): replaced, added free to the next
 * order, or refunded, in any mix, but never more than the line itself. Each is counted as a
 * share of the order line: the units replaced or added to the next order, and the part of its
 * invoiced amount refunded. A replacement that has left the warehouse can be claimed again,
 * it may have arrived broken too. Claims are taken for 60 days after the order was dispatched.
 */
class CheckClaimCompensation
{
    use AsAction;

    public const int MAX_AGE_DAYS = 60;

    private const float TOLERANCE = 0.001;

    /**
     * @return array<int, float> what is left to claim of each order line, as a share of it
     */
    public function handle(Order $order): array
    {
        $deliveryNotes = $order->deliveryNotes()
            ->where('state', '!=', DeliveryNoteStateEnum::CANCELLED)
            ->with('deliveryNoteItems')
            ->get();

        $originals = $deliveryNotes->where('type', DeliveryNoteTypeEnum::ORDER)
            ->flatMap->deliveryNoteItems
            ->whereNotNull('transaction_id')
            ->keyBy(fn (DeliveryNoteItem $item) => $item->transaction_id.'-'.$item->org_stock_id);

        $shareOfOriginal = function (DeliveryNoteItem $item, float $quantity) use ($originals): float {
            $original = (float) $originals->get($item->transaction_id.'-'.$item->org_stock_id)?->quantity_required;

            return $original > 0 ? $quantity / $original : 0;
        };

        $replacementItems = $deliveryNotes->where('type', DeliveryNoteTypeEnum::REPLACEMENT)
            ->flatMap(fn ($deliveryNote) => $deliveryNote->deliveryNoteItems->map(fn (DeliveryNoteItem $item) => [$item, $deliveryNote->state === DeliveryNoteStateEnum::DISPATCHED]))
            ->filter(fn (array $row) => $row[0]->transaction_id)
            ->groupBy(fn (array $row) => $row[0]->transaction_id);

        $transactions = $order->transactions()->where('model_type', 'Product')->get()->keyBy('id');

        $followOns = UpcomingTransaction::query()
            ->where('type', UpcomingTransactionTypeEnum::FOLLOW_ON)
            ->whereIn('source_transaction_id', $transactions->keys())
            ->get()
            ->groupBy('source_transaction_id')
            ->map(function ($followOns, $transactionId) use ($transactions) {
                $ordered = (float) $transactions[$transactionId]->quantity_ordered + (float) $transactions[$transactionId]->quantity_bonus;

                return $ordered > 0 ? $followOns->sum(fn (UpcomingTransaction $followOn) => (float) $followOn->quantity) / $ordered : 1;
            });

        $refunded = InvoiceTransaction::query()
            ->whereIn('invoice_id', $order->invoices()->where('type', InvoiceTypeEnum::INVOICE)->pluck('id'))
            ->whereIn('transaction_id', $transactions->keys())
            ->with('transactionRefunds')
            ->get()
            ->groupBy('transaction_id')
            ->map(fn ($lines) => $lines->sum(fn (InvoiceTransaction $line) => (float) $line->net_amount != 0.0
                ? abs((float) $line->transactionRefunds->where('in_process', false)->sum('net_amount')) / abs((float) $line->net_amount)
                : 0));

        return $transactions->keys()->mapWithKeys(function (int $transactionId) use ($replacementItems, $shareOfOriginal, $followOns, $refunded) {
            $replacements = $replacementItems->get($transactionId, collect());
            $byStock      = $replacements->groupBy(fn (array $row) => $row[0]->org_stock_id);
            $replaced     = $byStock->map(fn ($rows) => $rows->sum(fn (array $row) => $shareOfOriginal($row[0], (float) $row[0]->quantity_required)))->max() ?? 0;
            $resent       = $byStock->map(fn ($rows) => $rows->filter(fn (array $row) => $row[1])->sum(fn (array $row) => $shareOfOriginal($row[0], (float) $row[0]->quantity_dispatched)))->max() ?? 0;

            return [$transactionId => max(0, 1 + $resent - $replaced - ($followOns[$transactionId] ?? 0) - ($refunded[$transactionId] ?? 0))];
        })->all();
    }

    public static function isTooOld(Order $order): bool
    {
        return (bool) $order->dispatched_at?->lt(now()->subDays(self::MAX_AGE_DAYS));
    }

    /**
     * @param  array<int, float>  $claimedShares  share of each order line asked for, by transaction id
     */
    public static function ensure(Order $order, array $claimedShares, bool $checkAge = true): void
    {
        if ($checkAge && self::isTooOld($order)) {
            throw ValidationException::withMessages(['delivery_note_items' => __('Claims are taken for :days days after the order is dispatched', ['days' => self::MAX_AGE_DAYS])]);
        }

        $left = self::run($order);

        $over = collect($claimedShares)
            ->filter(fn (float $share, int $transactionId) => $share > ($left[$transactionId] ?? 1) + self::TOLERANCE)
            ->keys();

        if ($over->isNotEmpty()) {
            $codes = Transaction::whereIn('id', $over)->with('asset:id,code')->get()->map(fn (Transaction $transaction) => $transaction->asset?->code)->filter()->implode(', ');

            throw ValidationException::withMessages(['delivery_note_items' => __('More than what is left to claim of :codes: it is already replaced, refunded or waiting in their next order', ['codes' => $codes])]);
        }
    }
}
