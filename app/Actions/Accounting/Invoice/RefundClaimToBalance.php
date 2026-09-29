<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\Invoice\UI\FinaliseRefund;
use App\Actions\Accounting\InvoiceTransaction\StoreRefundInvoiceTransaction;
use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Ordering\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Refunds the lines of a claim to the customer's balance in one go: a refund of the order's
 * invoice holding just those lines, finalised and paid out as credit, the same steps the
 * refund page takes one at a time. Paying out is asked of the original invoice, which settles
 * its unpaid refunds. The claim counts in delivery note units, which are
 * not always the invoice's (a pack, a piece), so each line refunds the share of its invoice
 * line that was claimed: two of six units refund a third of what that line was invoiced at.
 */
class RefundClaimToBalance extends OrgAction
{
    private Order $order;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->order->shop_id}.edit");
    }

    public function rules(): array
    {
        return [
            'delivery_note_items'            => ['required', 'array', 'min:1'],
            'delivery_note_items.*.id'       => ['required', 'integer'],
            'delivery_note_items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @param  array<int, array{id: int, quantity: float|int}>  $claimedItems
     *
     * @throws \Throwable
     */
    public function handle(Order $order, array $claimedItems): Invoice
    {
        $shares = self::sharesByTransaction($order, $claimedItems);

        $invoice = $order->invoices()->where('type', InvoiceTypeEnum::INVOICE)->where(fn ($query) => $query->where('in_process', false)->orWhereNull('in_process'))->latest('id')->first();
        if (!$invoice || $shares === []) {
            throw ValidationException::withMessages(['delivery_note_items' => __('This order has no invoice to refund from')]);
        }

        $amounts = $invoice->invoiceTransactions()
            ->whereIn('transaction_id', array_keys($shares))
            ->with('transactionRefunds')
            ->get()
            ->mapWithKeys(function (InvoiceTransaction $invoiceTransaction) use ($shares) {
                $left = round((float) $invoiceTransaction->net_amount - abs((float) $invoiceTransaction->transactionRefunds->where('in_process', false)->sum('net_amount')), 2);

                return [$invoiceTransaction->id => [$invoiceTransaction, min($left, round((float) $invoiceTransaction->net_amount * $shares[$invoiceTransaction->transaction_id], 2))]];
            })
            ->filter(fn (array $line) => $line[1] > 0);

        if ($amounts->isEmpty()) {
            throw ValidationException::withMessages(['delivery_note_items' => __('These lines are not on the invoice, or are already refunded in full')]);
        }

        return DB::transaction(function () use ($invoice, $amounts) {
            $refund = StoreRefund::make()->action($invoice, []);

            $amounts->each(fn (array $line) => StoreRefundInvoiceTransaction::make()->action($refund, $line[0], ['net_amount' => $line[1]]));

            $refund = FinaliseRefund::make()->action($refund->refresh(), []);

            RefundToCredit::make()->action($invoice->refresh(), ['amount' => abs((float) $refund->refresh()->total_amount)]);

            return $refund->refresh();
        });
    }

    /**
     * The share of each order line being claimed, at most all of it. A product made of several
     * stocks has one delivery note line each; the largest share claimed of any of them counts.
     *
     * @param  array<int, array{id: int, quantity: float|int}>  $claimedItems
     * @return array<int, float>
     */
    public static function sharesByTransaction(Order $order, array $claimedItems): array
    {
        $quantities = collect($claimedItems)->mapWithKeys(fn (array $item) => [(int) $item['id'] => (float) $item['quantity']]);

        return DeliveryNoteItem::query()
            ->whereIn('id', $quantities->keys())
            ->whereIn('delivery_note_id', $order->deliveryNotes()->pluck('delivery_notes.id'))
            ->whereNotNull('transaction_id')
            ->where('quantity_required', '>', 0)
            ->get()
            ->groupBy('transaction_id')
            ->map(fn ($items) => min(1, $items->max(fn (DeliveryNoteItem $item) => $quantities[$item->id] / (float) $item->quantity_required)))
            ->all();
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Invoice
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData['delivery_note_items']);
    }

    public function jsonResponse(Invoice $refund): JsonResponse
    {
        return response()->json([
            'reference' => $refund->reference,
            'amount'    => abs((float) $refund->total_amount),
            'currency'  => $refund->currency?->code,
        ]);
    }
}
